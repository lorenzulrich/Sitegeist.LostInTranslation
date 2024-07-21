# Sitegeist.LostInTranslation
## Automatic Translations for Neos via DeepL API

Documents and contents are translated automatically once editors choose to "create and copy" a version in another language.
The included DeepLTranslationService can be used for other purposes as well.

The development was a collaboration of Sitegeist and Code Q.

### Authors & Sponsors

* Martin Ficzel - ficzel@sitegeist.de
* Felix Gradinaru - fg@codeq.at

*The development and the public-releases of this package is generously sponsored
by our employers http://www.sitegeist.de and http://www.codeq.at.*

## Installation

Sitegeist.LostInTranslation is available via packagist. Run `composer require sitegeist/lostintranslation`.

We use semantic-versioning so every breaking change will increase the major-version number.

## How it works

By default, all inline-editable properties are translated using DeepL (see Setting `translateInlineEditables`).
To include other `string` properties into the automatic translation, `options.automaticTranslation: true`
can be used in the property configuration. Also, you can disable automatic translation in general for certain node types
by setting `options.automaticTranslation: false`.

Some very common fields from `Neos.Neos:Document` are already configured to do so by default.

```yaml
'Neos.Neos:Document':
  options:
      automaticTranslation: true
  properties:
    title:
      options:
        automaticTranslation: true
    titleOverride:
      options:
        automaticTranslation: true
    metaDescription:
      options:
        automaticTranslation: true
    metaKeywords:
      options:
        automaticTranslation: true
```

Furthermore, automatic translation for all types derived from `Neos.Neos:Node` is enabled by default:

```yaml
'Neos.Neos:Node':
  options:
      automaticTranslation: true
```

## Configuration

This package needs an authenticationKey for the DeepL API from https://www.deepl.com/pro-api.
There are free plans that support a limited number of characters, but for productive use we recommend using a paid plan.

```yaml
Sitegeist:
  LostInTranslation:
    DeepLApi:
      authenticationKey: '.........................'
```

The translation of nodes can be configured via settings:

```yaml
Sitegeist:
  LostInTranslation:
    nodeTranslation:
      #
      # Enable the automatic translations of nodes while they are adopted to another dimension
      #
      enabled: true

      #
      # Translate all inline-editable fields without further configuration.
      #
      # If this is disabled, inline editables can be configured for translation by setting
      # `options.translateOnAdoption: true` for each property separately
      #
      translateInlineEditables: true

      #
      # The name of the language dimension. Usually needs no modification
      #
      languageDimensionName: 'language'
```

To enable automated translations for a language preset, set `options.translationStrategy` to `once`, `sync` or `none`.
The default mode is `once`;

* `once` will translate the node only once when the editor switches the language in the backend while editing this node. This is useful if you want to get an initial translation, but work on the different variants on your own after that.
* `sync` will translate and sync the node every time the node is published in the default language. Thus, it will not make sense to edit the node variant in an automatically translated language using this option, as your change will be overwritten every time.
* `none` will not translate variants for this dimension.

If a preset of the language dimension uses a locale identifier that is not compatible with DeepL, the deeplLanguage can
be configured explicitly for this preset via `options.deeplLanguage`.

```yaml
Neos:
  ContentRepository:
    contentDimensions:
      'language':

        #
        # The `defaultPreset` marks the source of all translations with mode `sync`
        #
        label: 'Language'
        default: 'en'
        defaultPreset: 'en'

        presets:

          #
          # English is the main language of the editors and spoken by editors,
          # the automatic translation is therefore disabled
          #
          'en':
            label: 'English'
            values: ['en']
            uriSegment: 'en'
            options:
              translationStrategy: 'none'

          #
          # Danish uses a different locale identifier than DeepL, so the `deeplLanguage` has to be configured explicitly
          # Here we use the "once" strategy, which will translate nodes only once when switching the language
          #
          'dk':
            label: 'Dansk'
            values: ['dk']
            uriSegment: 'dk'
            options:
              deeplLanguage: 'da'
              translationStrategy: 'once'

          #
          # For German, we want to have a steady sync of nodes
          #
          'de':
            label: 'Bayrisch'
            values: ['de']
            uriSegment: 'de'
            options:
              translationStrategy: 'sync'

          #
          # The bavarian language is not supported by DeepL and is disabled
          #
          'de_bar':
            label: 'Bayrisch'
            values: ['de_bar','de']
            uriSegment: 'de_bar'
            options:
              translationStrategy: 'none'
```

### Ignoring Terms

You can define terms that should be ignored by DeepL in the configuration.
The terms will are evaluated case-insensitive when searching for them, however
they will always be replaced with their actual occurrence.

This is how an example configuration could look like:

```yaml
Sitegeist:
  LostInTranslation:
    DeepLApi:
      ignoredTerms:
        - 'Sitegeist'
        - 'Neos.io'
        - 'Hamburg'
```

## Eel Helper

The package also provides two Eel helpers to translate texts in Fusion.

**:warning: Every one of these Eel helpers make an individual request to DeepL.** Thus having many of them on one page can significantly slow down the performance if the page is uncached.
:bulb: Only use while the [translation cache](#translation-cache) is enabled!

To translate a single text you can use:

```neosfusion
# ${Sitegeist.LostInTranslation.translate(string textToBeTranslated, string targetLanguage, string|null sourceLanguage = null): string}
${Sitegeist.LostInTranslation.translate('Hello world!', 'de', 'en')}
# Output: Hallo Welt!
```

To translate an array of texts you can use:

```neosfusion
# ${Sitegeist.LostInTranslation.translate(array textsToBeTranslated, string targetLanguage, string|null sourceLanguage = null): array}
${Sitegeist.LostInTranslation.translate(['Hello world!', 'My name is...'], 'de', 'en')}
# Output: ['Hallo Welt!', 'Mein Name ist...']
```

### Compare and update translations

The package contains two prototypes that visualize differences between the current and the `default`
translation.

To show the information in the backend you can render the `Sitegeist.LostInTranslation:Collection.TranslationInformation` adjacent to a ContentCollection.

```
content = Neos.Fusion:Join {
     info = Sitegeist.LostInTranslation:Collection.TranslationInformation {
          nodePath = 'content'
     }
     content = Neos.Neos:ContentCollection {
          nodePath = 'content'
     }
}
```

![DDEV__WebPage_test](https://github.com/sitegeist/Sitegeist.LostInTranslation/assets/1309380/7d268e18-5a2a-4292-8844-4800020b0ddb)

### `Sitegeist.LostInTranslation:Document.TranslationInformation`

Show information about missing and outdated translations on document level. Allows to "translate missing" and "update outdated" nodes.
The prototype is only showing in backend + edit mode.

- `node`:  (Node, default `documentNode` from Fusion context) The document node that shall be compared
- `referenceLanguage`: (string, default language preset) The preset used to compare against

### `Sitegeist.LostInTranslation:Collection.TranslationInformation`

Show information about missing and outdated translations on content collection level. Allows to "translate missing" and "update outdated" nodes.
The prototype is only showing in backend + edit mode.

- `nodePath`: (string, default null)
- `node`:  (Node, default `node` from Fusion context)
- `referenceLanguage`: (string, default language preset) The preset used to compare against

### Translation Cache

The plugin includes a translation cache for the DeepL API that stores the individual text parts
and their translated result for up to one week.
By default, the cache is enabled. To disable the cache, you need to set the following setting:

```yaml
Sitegeist:
  LostInTranslation:
    DeepLApi:
      enableCache: false
```

## Glossary

By using the DeepL API Glossary, you can improve the translation quality by providing specific translations for certain terms. Note that glossaries created with the DeepL API are distinct from glossaries created via the DeepL website.

You can configure language pairs from the [list of supported languages](https://developers.deepl.com/docs/api-reference/glossaries). Also, you can configure a language to sort the glossary entries, which usually would be your primary language:

```yaml
Sitegeist:
  LostInTranslation:
    DeepLApi:
      glossary:
        backendModule:
          # The language the entries are sorted by
          sortByLanguage: 'EN'
        # The configured language pairs
        # If you translate to both DE and FR from EN, you need to configure two entries
        languagePairs:
          -
            source: 'EN'
            target: 'DE'
          -
            source: 'EN'
            target: 'FR'
```
It also relies on the `deeplLanguage` configuration of the ContentRepository configuration (see above).

Because glossaries are immutable, a new glossary needs to created on each change. Therefore, the glossary feature has an own table holding the glossary entries. Configure a cronjob to regularly update the glossary if it is outdated:

```bash
./flow glossary:sync
```

By default, access to the Glossary module is granted to `Neos.Neos:Administrator`. You can change this by adding `Sitegeist.LostInTranslation:GlossaryEditor` as `parentRole` of an existing role or grant access to the `Sitegeist.LostInTranslation:BackendModule.Glossary` privilegeTarget.

## Performance

For every node to be translated, a single request is made to the DeepL API. This can lead to significant delay when documents with lots of nodes are translated. It is likely that future versions will improve this.

## Contribution

We will gladly accept contributions. Please send us pull requests.

## Changelog

### 2.0.0

* The preset option `translationStrategy` was introduced. There are now two auto-translation strategies
  * Strategy `once` will auto-translate the node once "on adoption", i.e. the editor switches to a different language dimension
  * Strategy `sync` will auto-translate and sync the node every time a node is updated in the default preset language
* The node setting `options.translateOnAdoption` has been renamed to `options.automaticTranslation`
* The new node option `options.automaticTranslation` was introduced
