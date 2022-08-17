# TYPO3 Extension `limit_to_pages`

This extension provides a simple command to generate automatically `limitToPages` configuration for "Extbase" route enhancers.

## Command

```
./vendor/bin/typo3 limit_to_pages:generate
```

### Options

`--mode | -m`

Defines behaviour during generation. Possible values are `hard`, `merge` (default) and `soft`.

| Mode    | Description                                                                                          |
|---------|------------------------------------------------------------------------------------------------------|
| `hard`  | The command find all pages to be limited and generated a configuration for each route enhancers.     |
| `merge` | The command find all pages to be limited and merged them with existing one for each route enhancers. |
| `soft`  | The command skip route enhancers with an existing configuration.                                     |

- Accept value: yes
- Is value required: yes
- Is multiple: no
- Default: `merge`

`--skip-site`

Site identifier to skip.

- Accept value: yes
- Is value required: yes
- Is multiple: yes

`--sort | -s`

Sort route enhancers by identifier.

- Accept value: no
- Is multiple: no
- Default: `false`

### Usage

The command generates a configuration file for each site configured in the "Sites" module.
To use it in your project, you can simply import the file in your `config.yaml` file.

For example if your site identifier is `foo` you can import the file like this:

```
imports:
  - resource: "EXT:limit_to_pages/Configuration/Routing/foo.yaml"
```
