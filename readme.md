# Taxonomy editor
Le missing visual taxonomy editor for bolt CMS.

This plugin may change the YAML syntax of your taxonomy.yml file, but it is still outputting valid YAML - it's just the syntax the parser has decided to use for the content it is outputting. 

## Permission

You can set the permission for the taxonomy editor in the configuration. The value to set can be chosen from the permission levels available on `[your-site]/bolt/roles` or you can create your own at `[your-site]/bolt/file/edit/config/permissions.yml`

For creating your own permission schema it is preferable to prefix it like: `ext:taxonomyeditor`

## Backups
Enabling / Disabling backups can be controlled in the config. 
There is an option to set the number of backups you wish to keep. 

## Support
If you run into issues or need a new feature, please open a ticket or fix it yourself, pull-requests are very welcome.

When adding new features, keep the next things in mind:
* Make it possible to toggle it on and off in the config (with a sensible default), as not all users - especially ordinary editors - might need to access it.
* All strings shown to users need to be translatable, with the english version also set as default.
