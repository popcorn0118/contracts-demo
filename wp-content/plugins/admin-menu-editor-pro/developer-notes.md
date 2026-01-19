# Notes to self

### IE11 does not need to be supported
WordPress 5.8 dropped support for IE11: 
https://wordpress.org/news/2021/05/dropping-support-for-internet-explorer-11/

### PHP 5.6.20
WordPress 6.2 still supports PHP 5.6.20 even though it recommends PHP 7.4+.

### Serialization naming conventions
`deserialize` is more popular and general than `unserialize`, at least outside PHP. Consider using `serialize` and `deserialize` in TypeScript code; maybe also in new PHP classes.

