<?php
/**
 * Shared order selection for the admin orders listing.
 *
 * Checkboxes come from the leading-column notifiers. The row passed to
 * NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_DATA supplies orders_id.
 *
 * The action row is one .row. Other plugins append their buttons by
 * observing NOTIFY_BATCH_ORDER_SELECT_BUTTONS and appending to $p2.
 * They should not emit their own row. The clicked button is the action.
 *
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

class zcObserverBatchOrderSelect extends base
{
    public function __construct()
    {
        $this->attach($this, [
            'NOTIFY_ADMIN_ORDERS_LISTING_BUTTONS',
            'NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_HEADING',
            'NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_DATA',
        ]);
    }

    /**
     * @param mixed $p1
     * @param mixed $p2
     * @param mixed $p3
     * @param mixed $p4
     * @param mixed $p5
     * @param mixed $p6
     * @param mixed $p7
     * @param mixed $p8
     * @param mixed $p9
     */
    public function notify_admin_orders_listing_buttons(&$class, string $eventID, $p1, &$p2, &$p3, &$p4, &$p5, &$p6, &$p7, &$p8, &$p9): void
    {
        if (!is_string($p2)) {
            $p2 = '';
        }
        $buttons = '';
        if (is_object($class) && method_exists($class, 'notify')) {
            $class->notify('NOTIFY_BATCH_ORDER_SELECT_BUTTONS', ['context' => 'orders_listing'], $buttons);
        }
        $p2 .= $this->actionRow(is_string($buttons) ? $buttons : '');
    }

    /**
     * @param mixed $p1
     * @param mixed $p2
     * @param mixed $p3
     * @param mixed $p4
     * @param mixed $p5
     * @param mixed $p6
     * @param mixed $p7
     * @param mixed $p8
     * @param mixed $p9
     */
    public function notify_admin_orders_list_leading_column_heading(&$class, string $eventID, $p1, &$p2, &$p3, &$p4, &$p5, &$p6, &$p7, &$p8, &$p9): void
    {
        $this->appendCell($p2, [
            'align' => 'center',
            'class' => 'noprint zc-bos-col',
            'text' => '<input type="checkbox" class="zc-bos-all" aria-label="'
                . zen_output_string_protected(TEXT_BATCH_ORDER_SELECT_ALL)
                . '">',
        ]);
    }

    /**
     * @param mixed $p1 unused sidebar order ($oInfo)
     * @param mixed $p2 the listing row ($orders->fields). Not written.
     * @param mixed $p3 leading-column cells. Append only.
     * @param mixed $p4
     * @param mixed $p5
     * @param mixed $p6
     * @param mixed $p7
     * @param mixed $p8
     * @param mixed $p9
     */
    public function notify_admin_orders_list_leading_column_data(&$class, string $eventID, $p1, &$p2, &$p3, &$p4, &$p5, &$p6, &$p7, &$p8, &$p9): void
    {
        if (!is_array($p2) || !array_key_exists('orders_id', $p2)) {
            return;
        }
        $this->appendCell($p3, $this->checkboxCell((int)$p2['orders_id']));
    }

    private function checkboxCell(int $orderId): array
    {
        $label = TEXT_BATCH_ORDER_SELECT_ORDER . ' ' . $orderId;

        return [
            'align' => 'center',
            'class' => 'noprint dataTableButtonCell zc-bos-col',
            'text' => '<input type="checkbox" class="zc-bos-check" value="' . $orderId . '" aria-label="' . zen_output_string_protected($label) . '">',
        ];
    }

    /**
     * @param mixed $bucket
     */
    private function appendCell(&$bucket, array $cell): void
    {
        if ($bucket === false || $bucket === null) {
            $bucket = [];
        }
        if (!is_array($bucket)) {
            return;
        }
        $bucket[] = $cell;
    }

    private function actionRow(string $buttons): string
    {
        $selected = str_replace('%s', '0', TEXT_BATCH_ORDER_SELECT_SELECTED);

        return '<div class="row noprint zc-bos-bar"><div class="col-xs-12">'
            . '<div class="zc-bos-actions">'
            . $buttons
            . '<span class="zc-bos-count">' . zen_output_string_protected($selected) . '</span>'
            . '<span class="zc-bos-hint">' . zen_output_string_protected(TEXT_BATCH_ORDER_SELECT_SHIFT) . '</span>'
            . '</div></div></div>';
    }
}
