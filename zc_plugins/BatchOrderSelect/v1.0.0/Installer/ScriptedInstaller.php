<?php
/**
 * On Zen Cart 2.2.2 and earlier, admin/orders.php does not notify observers
 * for a column before the order id. This installer copies that file to
 * orders.php_bos_backup and adds the two leading-column notifiers.
 *
 * The first install attempt only warns. Install is not finished until the
 * admin clicks Install again. Cancel on the install screen leaves the file
 * unchanged. Zen Cart 2.3 and newer are not modified.
 *
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    private const CONFIRM_KEY = 'batch_order_select_orders_patch';
    private const CONFIRM_SECONDS = 900;
    private const BACKUP_NAME = 'orders.php_bos_backup';

    protected function executeInstall()
    {
        if (!$this->ensureOrdersNotifiers()) {
            return false;
        }
        return parent::executeInstall();
    }

    protected function executeUninstall()
    {
        return parent::executeUninstall();
    }

    protected function executeUpgrade($oldVersion)
    {
        if (!$this->ensureOrdersNotifiers()) {
            return false;
        }
        return parent::executeUpgrade($oldVersion);
    }

    private function ensureOrdersNotifiers(): bool
    {
        if (!$this->versionNeedsOrdersPatch()) {
            unset($_SESSION[self::CONFIRM_KEY]);
            return true;
        }

        $path = $this->ordersPath();
        if (!is_file($path) || !is_readable($path)) {
            $this->fail('admin/orders.php was not found, so the leading-column notifiers were not added.');
            return false;
        }

        $source = file_get_contents($path);
        if (!is_string($source) || $source === '') {
            $this->fail('admin/orders.php could not be read, so it was not changed.');
            return false;
        }

        $plan = $this->planPatch($source);
        if ($plan['complete']) {
            unset($_SESSION[self::CONFIRM_KEY]);
            return true;
        }
        if ($plan['error'] !== '') {
            $this->fail($plan['error']);
            return false;
        }

        $directory = dirname($path);
        if (!is_writable($path) || !is_writable($directory)) {
            $this->fail('admin/orders.php is not writable. Its directory must be writable as well, so a backup named ' . self::BACKUP_NAME . ' can be saved. Nothing was changed.');
            return false;
        }

        if (!$this->patchConfirmed()) {
            $this->askForConfirmation($plan['changes']);
            return false;
        }

        $patched = $this->applyPatch($source, $plan['changes']);
        if ($patched === null) {
            $this->fail('admin/orders.php could not be updated. Nothing was changed.');
            return false;
        }

        $backup = $this->backupPath($directory);
        if (!copy($path, $backup)) {
            $this->fail('The backup of admin/orders.php could not be written. The original file was not changed.');
            return false;
        }

        $temporary = $path . '.bos_new';
        if (file_put_contents($temporary, $patched) === false) {
            $this->fail('The updated admin/orders.php could not be written. The original file was not changed. A backup was saved as admin/' . basename($backup) . '.');
            return false;
        }
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            $this->fail('The updated admin/orders.php could not replace the original. The original file was not changed. A backup was saved as admin/' . basename($backup) . '.');
            return false;
        }

        $this->note('Batch Order Select copied admin/orders.php to admin/' . basename($backup) . ' and added the leading-column notifiers.');
        return true;
    }

    /**
     * @return array{complete: bool, error: string, changes: list<string>}
     */
    private function planPatch(string $source): array
    {
        $hasHeading = strpos($source, 'NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_HEADING') !== false;
        $hasData = strpos($source, 'NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_DATA') !== false;
        $dataPassesRow = $this->dataNotifierPassesRow($source);
        $changes = [];

        if (!$hasHeading) {
            if ($this->countAnchors($source, $this->headingAnchor()) !== 1) {
                return [
                    'complete' => false,
                    'error' => 'admin/orders.php does not have the usual order-id heading, so it was not changed. Add NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_HEADING before that heading, then install again.',
                    'changes' => [],
                ];
            }
            $changes[] = 'heading';
        }
        if (!$hasData) {
            if ($this->countAnchors($source, $this->rowAnchor()) !== 1) {
                return [
                    'complete' => false,
                    'error' => 'admin/orders.php does not have the usual order-id cell, so it was not changed. Add NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_DATA before that cell, then install again.',
                    'changes' => [],
                ];
            }
            $changes[] = 'data';
        } elseif (!$dataPassesRow) {
            if ($this->notifierSpan($source, 'NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_DATA') === null) {
                return [
                    'complete' => false,
                    'error' => 'admin/orders.php mentions the row notifier, but the notify() call could not be updated. Nothing was changed.',
                    'changes' => [],
                ];
            }
            $changes[] = 'data-fix';
        }

        return [
            'complete' => $changes === [],
            'error' => '',
            'changes' => $changes,
        ];
    }

    /**
     * @param list<string> $changes
     */
    private function applyPatch(string $source, array $changes): ?string
    {
        if (in_array('data-fix', $changes, true)) {
            $source = $this->rewriteDataNotifier($source);
            if ($source === null) {
                return null;
            }
        }
        if (in_array('data', $changes, true)) {
            $source = $this->insertBeforeAnchor($source, $this->rowAnchor(), $this->dataBlock());
            if ($source === null) {
                return null;
            }
        }
        if (in_array('heading', $changes, true)) {
            $source = $this->insertBeforeAnchor($source, $this->headingAnchor(), $this->headingBlock());
            if ($source === null) {
                return null;
            }
        }
        return $source;
    }

    private function versionNeedsOrdersPatch(): bool
    {
        $major = defined('PROJECT_VERSION_MAJOR') ? (int)PROJECT_VERSION_MAJOR : 0;
        $minor = defined('PROJECT_VERSION_MINOR') ? (string)PROJECT_VERSION_MINOR : '';
        if ($major > 2) {
            return false;
        }
        if ($major < 2) {
            return true;
        }
        $parts = array_map('intval', explode('.', $minor));
        $minorNumber = $parts[0] ?? 0;
        if ($minorNumber > 2) {
            return false;
        }
        // 2.2.0 through 2.2.2 all report PROJECT_VERSION_MINOR as "2.2".
        // A later 2.2 release that already has the notifiers is left alone.
        return true;
    }

    private function dataNotifierPassesRow(string $source): bool
    {
        $span = $this->notifierSpan($source, 'NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_DATA');
        if ($span === null) {
            return false;
        }
        $statement = substr($source, $span[0], $span[1] - $span[0] + 1);
        return strpos($statement, '$orders->fields') !== false;
    }

    private function rewriteDataNotifier(string $source): ?string
    {
        $span = $this->notifierSpan($source, 'NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_DATA');
        if ($span === null) {
            return null;
        }
        $replacement = "->notify('NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_DATA', (\$oInfo ?? []), \$orders->fields, \$leading_data);";
        return substr($source, 0, $span[0]) . $replacement . substr($source, $span[1] + 1);
    }

    private function insertBeforeAnchor(string $source, string $pattern, string $block): ?string
    {
        if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        $offset = $match[0][1];
        $lineStart = strrpos(substr($source, 0, $offset), "\n");
        $lineStart = ($lineStart === false) ? 0 : $lineStart + 1;
        $indent = substr($source, $lineStart, $offset - $lineStart);
        if (!preg_match('/^[ \t]*$/', $indent)) {
            $indent = '';
            $lineStart = $offset;
        }
        $newline = strpos($source, "\r\n") !== false ? "\r\n" : "\n";
        $lines = explode("\n", str_replace("\r\n", "\n", trim($block, "\n")));
        $inserted = $indent . implode($newline . $indent, $lines) . $newline;
        return substr($source, 0, $lineStart) . $inserted . substr($source, $lineStart);
    }

    /**
     * @return array{0: int, 1: int}|null start of "->notify", end of the semicolon
     */
    private function notifierSpan(string $source, string $event): ?array
    {
        $pos = strpos($source, "'" . $event . "'");
        if ($pos === false) {
            $pos = strpos($source, '"' . $event . '"');
        }
        if ($pos === false) {
            return null;
        }
        $before = substr($source, 0, $pos);
        $notify = strrpos($before, '->notify(');
        if ($notify === false) {
            return null;
        }
        $open = strpos($source, '(', $notify);
        if ($open === false) {
            return null;
        }
        $depth = 0;
        $length = strlen($source);
        for ($i = $open; $i < $length; $i++) {
            $character = $source[$i];
            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
                if ($depth === 0) {
                    $semicolon = strpos($source, ';', $i);
                    if ($semicolon === false) {
                        return null;
                    }
                    return [$notify, $semicolon];
                }
            }
        }
        return null;
    }

    private function countAnchors(string $source, string $pattern): int
    {
        $count = preg_match_all($pattern, $source);
        return $count === false ? 0 : $count;
    }

    private function headingAnchor(): string
    {
        return '/<th class="dataTableHeadingContent text-center">\s*(?:<\?=|<\?php echo)\s*TABLE_HEADING_ORDERS_ID\s*;?\s*\?>\s*<\/th>/';
    }

    private function rowAnchor(): string
    {
        return '/<td class="dataTableContent text-center">\s*(?:<\?=|<\?php echo)\s*\$show_difference\s*\.\s*\$orders->fields\[\'orders_id\'\]\s*;?\s*\?>\s*<\/td>/';
    }

    private function headingBlock(): string
    {
        $block = <<<'PHP'
<?php
// -----
// Leading columns before the order id. Added by Batch Order Select.
// An observer appends to $leading_headings. Each entry may set align
// (left, right, or center), class, and text. Check for (bool)false first.
$leading_headings = false;
$zco_notifier->notify('NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_HEADING', [], $leading_headings);
if (is_array($leading_headings)) {
    foreach ($leading_headings as $heading_info) {
        $align = (isset($heading_info['align']) && in_array($heading_info['align'], ['left', 'right', 'center'], true))
            ? ' text-' . $heading_info['align'] : '';
        $class = isset($heading_info['class']) ? ' ' . preg_replace('/[^A-Za-z0-9_ -]/', '', $heading_info['class']) : '';
        echo '<th class="dataTableHeadingContent' . $align . $class . '">' . $heading_info['text'] . '</th>';
    }
}
PHP;
        return $block . "\n?" . '>';
    }

    private function dataBlock(): string
    {
        $block = <<<'PHP'
<?php
// -----
// Leading cells for this order. Added by Batch Order Select.
// $orders->fields is passed so the observer does not read a global.
// An observer appends to $leading_data. Check for (bool)false first.
$leading_data = false;
$zco_notifier->notify('NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_DATA', ($oInfo ?? []), $orders->fields, $leading_data);
if (is_array($leading_data)) {
    foreach ($leading_data as $data_info) {
        $align = (isset($data_info['align']) && in_array($data_info['align'], ['left', 'right', 'center'], true))
            ? ' text-' . $data_info['align'] : '';
        $class = isset($data_info['class']) ? ' ' . preg_replace('/[^A-Za-z0-9_ -]/', '', $data_info['class']) : '';
        echo '<td class="dataTableContent' . $align . $class . '">' . $data_info['text'] . '</td>';
    }
}
PHP;
        return $block . "\n?" . '>';
    }

    private function ordersPath(): string
    {
        if (defined('DIR_FS_ADMIN')) {
            return rtrim(DIR_FS_ADMIN, "/\\") . '/orders.php';
        }
        $admin = defined('DIR_WS_ADMIN') ? DIR_WS_ADMIN : 'admin/';
        return rtrim(DIR_FS_CATALOG, "/\\") . '/' . trim($admin, "/\\") . '/orders.php';
    }

    private function backupPath(string $directory): string
    {
        $preferred = $directory . '/' . self::BACKUP_NAME;
        if (!file_exists($preferred)) {
            return $preferred;
        }
        return $directory . '/' . self::BACKUP_NAME . '.' . gmdate('YmdHis');
    }

    /**
     * @param list<string> $changes
     */
    private function askForConfirmation(array $changes): void
    {
        $_SESSION[self::CONFIRM_KEY] = time();
        $message = 'Batch Order Select has not changed any files yet. Your version of Zen Cart doesn\'t have the notifiers required for this plugin. The installer will copy admin/orders.php to admin/'
            . self::BACKUP_NAME
            . ' and then add the required code. If you experience any problems after installation, it means the installer failed to apply these changes. Simply delete the new admin/orders.php file and restore the newly created backup by renaming ' . self::BACKUP_NAME . ' back to orders.php.<br><br>'
            . '<strong>Run Install again to apply this change.</strong> To cancel, click Cancel on the install screen and do not install. '
            . 'This confirmation expires in 15 minutes.';
        $this->errorContainer->addError(0, $message, false, $message);
    }

    private function patchConfirmed(): bool
    {
        if (empty($_SESSION[self::CONFIRM_KEY])) {
            return false;
        }
        $confirmedAt = (int)$_SESSION[self::CONFIRM_KEY];
        unset($_SESSION[self::CONFIRM_KEY]);
        return (time() - $confirmedAt) <= self::CONFIRM_SECONDS;
    }

    private function fail(string $message): void
    {
        unset($_SESSION[self::CONFIRM_KEY]);
        $this->errorContainer->addError(0, $message, false, $message);
    }

    private function note(string $message): void
    {
        if (isset($GLOBALS['messageStack']) && is_object($GLOBALS['messageStack'])) {
            $GLOBALS['messageStack']->add_session($message, 'success');
        }
    }
}
