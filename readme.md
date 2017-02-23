# Taxonomy editor

Le missing visual taxonomy editor for bolt CMS.

## Permission

You can set the permission for the taxonomy editor in the configuration. The value to set can be chosen from the permission levels available on `[your-site]/bolt/roles` or you can create your own at `[your-site]/bolt/file/edit/config/permissions.yml`

For creating your own permission schema it is preferable to prefix it like: `ext:taxonomyeditor`

## Support
If you run into issues or need a new feature, please open a ticket or fix it yourself, pull-requests are very welcome.

When adding new features, keep the next things in mind:
* Make it possible to toggle it on and off in the config (with a sensible default), as not all users - especially ordinary editors - might need to access it.
* All strings shown to users need to be translatable, with the english version also set as default.
