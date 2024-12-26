# InventoryManager

InventoryManager is an Admidio plugin for managing an organization's inventory.

## Features

- Add, edit, and delete inventory items
- Import and export inventory data
- Filter and search functions
- Notifications for inventory changes
- Support for multiple languages

## Installation

1. Download the plugin and extract it into the `adm_plugins/InventoryManager` directory.
2. Navigate to `adm_plugins/InventoryManager` and ensure all files are correctly extracted.
3. Open the Admidio web interface, log in, and navigate to the menu editing page. Ensure you have the necessary rights to edit menu entries.
4. Set up a custom menu entry in Admidio referring to `/adm_plugins/InventoryManager/inventory_manager.php`. You can also set a custom icon and organize the menu or the appearance in the sidebar.

## Usage

After installing and activating the plugin, you can manage the inventory through the Admidio menu. You can add new items, edit or delete existing ones, and use various filter and search functions.

## Configuration

Configuration is managed through the preferences button on the InventoryManager start page. Here you can adjust various settings and use specific plugin options:
- Edit and add item fields
- Export and import data
- Uninstall all data from the database (cleanup plugin)
- Modify user permissions for plugin usage

## Import and Export

The plugin supports importing and exporting inventory data in CSV and XLSX formats. You can access the corresponding functions through the preferences button on the InventoryManager start page.

## Notifications

The plugin sends notifications for inventory changes. Notifications will only be sent if they are set up by the system notifications settings of your organization and only to the roles specified for notification.

## Support and Documentation

For more information and support, refer to the [InventoryManager GitHub project page](https://github.com/MightyMCoder/InventoryManager).

## License

This plugin is licensed under the GNU General Public License v2.0. For more information, see the [LICENSE](LICENSE) file.

## Authors

- MightyMCoder

## Acknowledgements

- Based on KeyManager by rmbinder (https://github.com/rmbinder/KeyManager)
- Uses the external class XLSXWriter (https://github.com/mk-j/PHP_XLSXWriter)
