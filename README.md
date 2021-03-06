# ḅapaṭrẹn — The TYPO3 CMS 9 backend page tree enhancements

Since TYPO3 9.5.20, the performance improvements are part of the TYPO3 core, so you no longer need this extension!

## Requirements

You need "cweagans/composer-patches" to patch core files

## Add patch to extra section

```
  "extra": {
    …
    "patches": {
      "typo3/cms-backend": {
        "ḅapaṭrẹn typo3-cms/backend page tree patches": "https://raw.githubusercontent.com/plan2net/bapatren/<version number>/patches/backend.patch"
      }
    }
```

(replace the version string with the one that's compatible with your TYPO3 version)

# Update (for developers)

Tag with a version number corresponding to the version of TYPO3 CMS the patch applies.
