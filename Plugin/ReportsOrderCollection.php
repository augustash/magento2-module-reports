<?php

/**
 * Customized dashboard reports.
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2020 August Ash (https://www.augustash.com)
 */

namespace Augustash\Reports\Plugin;

use Augustash\Reports\Model\Config as ModuleConfig;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Reports\Model\ResourceModel\Order\Collection as Subject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;

/**
 * Order collection plugin class.
 */
class ReportsOrderCollection
{
    /**
     * @var string
     */
    protected $salesAmountExpression;

    /**
     * @var \Augustash\Reports\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $orderConfig;

    /**
     * Constructor.
     *
     * Initialize class dependencies.
     *
     * @param \Augustash\Reports\Model\Config $config
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     */
    public function __construct(
        ModuleConfig $config,
        OrderConfig $orderConfig
    ) {
        $this->config = $config;
        $this->orderConfig = $orderConfig;
    }

    /**
     * Around `calculateTotals` plugin.
     *
     * @param \Magento\Reports\Model\ResourceModel\Order\Collection $subject
     * @param callable $proceed
     * @param int $isFilter
     * @return \Magento\Reports\Model\ResourceModel\Order\Collection
     */
    public function aroundCalculateTotals(
        Subject $subject,
        callable $proceed,
        $isFilter = 0
    ): Subject {
        if (!$this->config->isEnabled()) {
            return $proceed($isFilter);
        }

        $subject->setMainTable('sales_order');
        $subject->removeAllFieldsFromSelect();
        $connection = $subject->getConnection();

        $statuses = $this->getExcludedStatuses();
        $baseTaxAmount = $connection->getIfNullSql('main_table.base_tax_amount', 0);
        $baseTaxRefunded = $connection->getIfNullSql('main_table.base_tax_refunded', 0);
        $baseShippingAmount = $connection->getIfNullSql('main_table.base_shipping_amount', 0);
        $baseShippingRefunded = $connection->getIfNullSql('main_table.base_shipping_refunded', 0);

        $revenueExp = $this->getSalesAmountExpression($connection);
        $taxExp = sprintf('%s - %s', $baseTaxAmount, $baseTaxRefunded);
        $shippingExp = sprintf('%s - %s', $baseShippingAmount, $baseShippingRefunded);

        if ($isFilter == 0) {
            $rateExp = $connection->getIfNullSql('main_table.base_to_global_rate', 0);
            $subject->getSelect()->columns(
                [
                    'revenue' => new \Zend_Db_Expr(sprintf('SUM((%s) * %s)', $revenueExp, $rateExp)),
                    'tax' => new \Zend_Db_Expr(sprintf('SUM((%s) * %s)', $taxExp, $rateExp)),
                    'shipping' => new \Zend_Db_Expr(sprintf('SUM((%s) * %s)', $shippingExp, $rateExp)),
                ]
            );
        } else {
            $subject->getSelect()->columns(
                [
                    'revenue' => new \Zend_Db_Expr(sprintf('SUM(%s)', $revenueExp)),
                    'tax' => new \Zend_Db_Expr(sprintf('SUM(%s)', $taxExp)),
                    'shipping' => new \Zend_Db_Expr(sprintf('SUM(%s)', $shippingExp)),
                ]
            );
        }

        $subject->getSelect()->columns(
            ['quantity' => 'COUNT(main_table.entity_id)']
        )->where(
            'main_table.status NOT IN(?)',
            $statuses
        )->where(
            'main_table.state NOT IN (?)',
            [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_HOLDED,
                Order::STATE_CANCELED,
            ]
        );

        return $subject;
    }

    /**
     * Around `calculateSales` plugin.
     *
     * @param \Magento\Reports\Model\ResourceModel\Order\Collection $subject
     * @param callable $proceed
     * @param int $isFilter
     * @return \Magento\Reports\Model\ResourceModel\Order\Collection
     */
    public function aroundCalculateSales(
        Subject $subject,
        callable $proceed,
        $isFilter = 0
    ): Subject {
        if (!$this->config->isEnabled()) {
            return $proceed($isFilter);
        }

        $statuses = $this->getExcludedStatuses();
        $subject->setMainTable('sales_order');
        $subject->removeAllFieldsFromSelect();
        $connection = $subject->getConnection();
        $expr = $this->getSalesAmountExpression($connection);

        if ($isFilter == 0) {
            $expr = '(' . $expr . ') * main_table.base_to_global_rate';
        }

        $subject->getSelect()->columns(
            ['lifetime' => "SUM({$expr})", 'average' => "AVG({$expr})"]
        )->where(
            'main_table.status NOT IN(?)',
            $statuses
        )->where(
            'main_table.state NOT IN(?)',
            [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_HOLDED,
                Order::STATE_CANCELED,
            ]
        );

        return $subject;
    }

    /**
     * Get array of status values to exclude from the report data.
     *
     * @return array
     */
    public function getExcludedStatuses(): array
    {
        $statuses = array_merge(
            $this->orderConfig->getStateStatuses(Order::STATE_CANCELED),
            $this->orderConfig->getStateStatuses(Order::STATE_HOLDED),
            $this->orderConfig->getStateStatuses(Order::STATE_NEW)
        );

        if (empty($statuses)) {
            $statuses = [0];
        }

        return $statuses;
    }

    /**
     * Get sales amount expression
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return string
     */
    public function getSalesAmountExpression(AdapterInterface $connection): string
    {
        if (null === $this->salesAmountExpression) {
            $this->salesAmountExpression = sprintf(
                '%s - %s - %s - (%s - %s - %s)',
                $connection->getIfNullSql('main_table.base_grand_total', 0),
                $connection->getIfNullSql('main_table.base_tax_amount', 0),
                $connection->getIfNullSql('main_table.base_shipping_amount', 0),
                $connection->getIfNullSql('main_table.base_total_refunded', 0),
                $connection->getIfNullSql('main_table.base_tax_refunded', 0),
                $connection->getIfNullSql('main_table.base_shipping_refunded', 0)
            );
        }

        return $this->salesAmountExpression;
    }
}
