# Batch Order Select for Zen Cart

**Platform:** Zen Cart 2.1.0+ (up to 3.0.0)  

> **Disclaimer:** Please note that this plugin is installed and used at your own risk. If you are unsure of how to complete the installation procedure documented below, please seek support in the official Forum section (keep in mind Forums are public and work on voluntary basis) or contact me for paid assistance. ZenExpert takes no responsibility for problems related to the installation or use of this plugin.

> **IMPORTANT: This plugin provides no standalone functionality.**  
> Batch Order Select is a foundational "skeleton" plugin. It does not process orders, print invoices, or update statuses by itself. Its sole purpose is to establish a shared, standardized checkbox column and a unified action bar. Other third-party plugins (such as *Batch Order Print*) require this base to be installed first so they can seamlessly hook their buttons into its interface.

Instead of every batch-processing module injecting its own duplicate scripts, redundant checkboxes, or stacked button rows into the Zen Cart admin orders listing (`Customers > Orders`), this plugin centralizes the UI architecture. It ensures a clean, conflict-free environment for all your batch management tools.

## Table of Contents
- [Installation & Core Notifier Patching](#installation--core-notifier-patching)
- [Features & Usage](#features--usage)
- [Developer Guide: Integrating Your Plugin](#developer-guide-integrating-your-plugin)
- [Common Issues](#common-issues)
- [Contributing](#contributing)
- [License](#license)

## Installation & Core Notifier Patching

Batch Order Select is built as an **Encapsulated Plugin** for Zen Cart 2.1.0 and higher. It creates no database tables or configuration keys.

> **Important Note on Zen Cart Versions:**  
> The leading-column notifiers (`NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_HEADING` and `NOTIFY_ADMIN_ORDERS_LIST_LEADING_COLUMN_DATA`) were officially added to Zen Cart core in **v2.3.0**. 
> - **Zen Cart 2.3.0 and newer:** 100% zero core file modifications. The installer detects the native notifiers and leaves `admin/orders.php` completely untouched.
> - **Zen Cart 2.1.0 – 2.2.2:** Because earlier versions do not include the leading-column notifiers in core, the scripted installer includes a safe, two-step confirmation process that automatically backs up `admin/orders.php` to `admin/orders.php_bos_backup` and injects the two missing notifiers.

1. Backup your store files and database.
2. Copy the `BatchOrderSelect` folder into your store's `zc_plugins` directory so the structure matches: `zc_plugins/BatchOrderSelect/v1.0.0/manifest.php`
3. In your Zen Cart Admin, navigate to **Modules > Plugin Manager**.
4. Locate **Batch Order Select** and click **Install**:
   - **On Zen Cart 2.3.0+:** The plugin installs immediately.
   - **On Zen Cart 2.1.0 – 2.2.2:** If `admin/orders.php` does not yet contain the leading-column notifiers, the first click on **Install** pauses without changing any files and displays a notice explaining the patch. Click **Install** a second time (within 15 minutes) to confirm and apply the patch, or click **Cancel** to abort. When confirmed, your original file is safely backed up before the notifiers are inserted.
5. Enable the plugin if the Plugin Manager does not automatically enable it for you.
6. Install any companion batch plugins (such as *Batch Order Print*) **after** installing Batch Order Select.

## Features & Usage

Once installed, navigate to **Customers > Orders** in your Zen Cart admin panel:

*   **Leading Checkbox Column:** A dedicated checkbox column appears as the very first column in the orders table, immediately before the Order ID. Each checkbox cell uses the `dataTableButtonCell` class so clicking a checkbox will not accidentally trigger the row click.
*   **Select All (Header Checkbox):** Clicking the checkbox in the table header selects or deselects every order listed on the current page. When only some orders are selected, the header checkbox displays an indeterminate state.
*   **Shift-Click Range Selection:** Click any order checkbox, hold down the `Shift` key, and click another checkbox further up or down the table to instantly toggle every order between them.
*   **Unified Action Row:** A single toolbar row (`.zc-bos-bar`) sits beneath the search filters and right above the orders table. It displays a live counter of how many orders are currently selected alongside a Shift-click reminder.
*   **Smart Button State Management:** All batch action buttons remain disabled until at least one order is checked, preventing accidental empty submissions.

## Developer Guide: Integrating Your Plugin

Batch Order Select is designed as a foundation for other Zen Cart plugins. Rather than adding your own checkbox column, your plugin simply adds its action button(s) to the shared toolbar and receives the selected Order IDs upon submission.

### How Shared Checkboxes Work
1. **One Set of Checkboxes for All Plugins:** Every row renders a single checkbox whose `value` is the `orders_id`. Checkboxes are not tied to a single wrapping `<form>`; each plugin places its own compact `<form>` inside the top action bar.
2. **Automatic Button Enabling/Disabling:** Any `button[type="submit"]` inside the `.zc-bos-actions` container starts with the `disabled` attribute and automatically enables when orders are checked.
3. **On-Submit Order ID Injection:** When the admin clicks your plugin's submit button, Batch Order Select writes one hidden `<input type="hidden">` per selected order directly into the submitted form right before it posts.
4. **Customizable Field Name:** By default, the inputs are named `batch_order_ids[]`. You can customize this using the `data-zc-bos-field` attribute.

### How to Append Action Buttons
Create an admin observer that listens for the `NOTIFY_BATCH_ORDER_SELECT_BUTTONS` event.
* **Event:** `NOTIFY_BATCH_ORDER_SELECT_BUTTONS`
* **$p1:** `['context' => 'orders_listing']`
* **$p2:** Passed by reference containing the accumulated HTML. **Append** your form HTML to `$p2`. Do NOT overwrite it or wrap it in a `.row`.

**Form Structure Rules:**
* Class: `zc-bos-form`
* Include an empty `<span class="zc-bos-ids"></span>` inside the form.
* Initial state: submit button(s) `disabled`.

### Complete Observer Example

```php
<?php
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

class zcObserverMyBatchPlugin extends base
{
    public function __construct()
    {
        $this->attach($this, [
            'NOTIFY_BATCH_ORDER_SELECT_BUTTONS',
        ]);
    }

    public function notify_batch_order_select_buttons(&$class, string $eventID, $p1, &$p2): void
    {
        if (!is_string($p2)) {
            $p2 = '';
        }

        $form = zen_draw_form(
            'my_batch_action',
            FILENAME_ORDERS,
            'action=my_batch_process',
            'post',
            'class="zc-bos-form" data-zc-bos-field="batch_order_ids[]"'
        );
        $form .= '<span class="zc-bos-ids"></span>';
        $form .= '<button type="submit" name="document" value="invoice" class="btn btn-primary btn-sm" disabled>'
            . 'Print Invoices'
            . '</button>';
        $form .= '</form>';

        $p2 .= $form;
    }
}
```

## Common Issues
* **Install paused on Zen Cart 2.1.0 – 2.2.2:** This is an intentional safety feature. Simply click **Install** a second time within 15 minutes to complete the installation.
* **"admin/orders.php is not writable" error:** On Zen Cart 2.2.2 and earlier, ensure that both `admin/orders.php` and your `admin/` directory have write permissions.

## Contributing
Found a bug? Feel free to submit an issue or pull request.

## License
GNU Public License V2.0
