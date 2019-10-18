# ḅapaṭrẹn — The TYPO3 CMS 9 backend page tree enhancements

## Requirements

You need "cweagans/composer-patches" to patch core files

## Add patch to extra section

```
  "extra": {
    …
    "patches": {
      "typo3/cms-backend": {
        "ḅapaṭrẹn typo3-cms/backend page tree patches": "https://raw.githubusercontent.com/plan2net/bapatren/1.2.0/patches/backend.patch"
      }
    }
```

(replace the version string with the one that's compatible with your TYPO3 version)
