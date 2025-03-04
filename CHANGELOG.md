# Content Buddy - Changelog

All notable changes to this project will be documented in this file.

## 1.2.1 - 2025-03-04
### Added
- Added the ability to translate Assets.

### Fixed
- Fixed and issue when translating Categories to all languages.

## 1.2.0 - 2025-02-21
### Added
- Added DeepL integration, available in text translation models.
- Added o1 and o3-mini to the available models for OpenAI.
- Added Stable Diffusion 3.5 models to the available list for image generation.
- Added the ability to translate Categories.

### Changed
- Separated the translation and generation settings into two separate tabs in the plugin settings.
- Allow the user to set delays for bulk translations.

### Fixed
- Fixed an issue with license notices displaying when they shouldn't.

## 1.1.14 - 2025-01-13
### Fixed
- Fixed an issue that prevented the Users and Assets sections from opening. [Issue #35](https://github.com/convergine/craft-content-buddy/issues/35)

## 1.1.13 - 2025-01-06
### Added
- Added an option to translate content to all languages at once.
- Allow translating of entry slugs. [Issue #28](https://github.com/convergine/craft-content-buddy/issues/28)

## 1.1.12 - 2024-12-09
### Changed
- Allow changing the order of the prompts in the Quick Menu. [Issue #32](https://github.com/convergine/craft-content-buddy/issues/32)
- Added an option to toggle the translation button in the Quick Menu. [Issue #33](https://github.com/convergine/craft-content-buddy/issues/33)

### Fixed
- Adjusted the max tokens limit for x.AI Grok to resolve usage issues.
- Resolved an issue where the title field from the Navigation plugin was not displaying the Quick Menu.
- Adjusted CK Editor field UI to display the Quick Menu properly. [Issue #34](https://github.com/convergine/craft-content-buddy/issues/34)
- Resolved an issue where translations could override the slug with the original site's slug.

## 1.1.11 - 2024-11-11
### Fixed
- Fixed error handling for x.AI processing.

## 1.1.10 - 2024-11-01
### Added
- Added x.AI Grok to the available models for text generation.

### Fixed
- Fixed a migration error related to project configurations.

## 1.1.9 - 2024-10-18
### Added
- Added support for DALL-E 3 and Stable Diffusion v3 engines in the Image Generation Settings.

### Changed
- Updated translation prompt parameters to support large-scale translations using the GPT-4o and GPT-4o-mini models, ensuring more efficient handling of complex multilingual content.

### Fixed
- Resolved and issue with keyboard shortcuts not working after running prompts. [Issue #30](https://github.com/convergine/craft-content-buddy/issues/30)
- Fixed a migration error related to project configurations.

## 1.1.8 - 2024-09-26
### Changed
- Removed choices for individual matrix field translation, allowing to toggle all matrix fields at once.

### Fixed
- Fixed an issue with modifying prompt instructions.

## 1.1.7 - 2024-09-09
### Fixed
- Hotfix for CraftCMS 4.x single-page and full-site translations.

## 1.1.6 - 2024-09-09
### Fixed
- Resolved issues with translation of multiple entry types and nested matrix fields in CraftCMS 5.x for both single-page and full-site translations.

## 1.1.5 - 2024-09-06
### Changed
- Exporting project configurations now utilizes config handles.
- Removed the word limit on prompt templates.

### Fixed
- Resolved matrix field translation issues in CraftCMS 5.x for both single-page and full-site translations. [Issue #27](https://github.com/convergine/craft-content-buddy/issues/27)

## 1.1.4 - 2024-08-28
### Added
- Added GPT-4o and GPT-4o mini to the selectable models in API settings.
- Added support for structures. [Issue #26](https://github.com/convergine/craft-content-buddy/issues/26)

### Fixed
- Resolved an issue where translating matrix fields would fail if those fields did not exist on the target site. Now, the error will no longer block the translation of other content. However, to ensure matrix fields are translated, you must modify the matrix field's "Propagation Method" on the source site. Change it to any option other than "Only save blocks to the site they are created in." This setting prevents fields from being copied to other languages, and since Content Buddy does not create fields, it cannot translate matrix fields that do not exist on the target site. [Issue #27](https://github.com/convergine/craft-content-buddy/issues/27)

## 1.1.3 - 2024-05-31
### Fixed
- Fixed issue with site translation on fresh install

## 1.1.2 - 2024-05-31
### Added
- Ability to translate single pages with a click [Issue #7](https://github.com/convergine/craft-content-buddy/issues/7)

## 1.1.1 - 2024-04-03
### Fixed
- Fixed issue with fresh installation [Issue #24](https://github.com/convergine/craft-content-buddy/issues/24)

## 1.1.0 - 2024-03-21
### Added
- Added support for CraftCMS v5.x
- Added support for CKEditor fields [Issue #21](https://github.com/convergine/craft-content-buddy/issues/21)

### Fixed
- Fixed error installing in Docker [Issue #22](https://github.com/convergine/craft-content-buddy/issues/22)

## 1.0.9 - 2024-03-05
### Fixed
- Fixed issue with TranslateRecord migration [Issue #20](https://github.com/convergine/craft-content-buddy/issues/20)

### Changed
- Updated to support the latest GPT models and Stability.AI engines.

## 1.0.8 - 2024-02-02
### Added
- Added auto-translate feature for multi-site CraftCMS instances

### Fixed
- Fixed issue when custom prompt template exceeds 255 characters [Issue #16](https://github.com/convergine/craft-content-buddy/issues/16)
- Fixed issue with multi-site [Issue #19](https://github.com/convergine/craft-content-buddy/issues/19)

## 1.0.7 - 2023-09-13
### Fixed
- Fixed issue with using translation prompt in 1.0.6 [Issue #15](https://github.com/convergine/craft-content-buddy/issues/15)

## 1.0.6 - 2023-08-24
### Fixed
- Fixed path that was hard coded in the plugin, causing issues with with non standard /admin path. [Issue #12](https://github.com/convergine/craft-content-buddy/issues/12)
- Fixed issue with translations not using the correct prompt. [Issue #13](https://github.com/convergine/craft-content-buddy/issues/13)
- Fixed incorrect ChatGPT error message display introduced in previous version. [Issue #14](https://github.com/convergine/craft-content-buddy/issues/14)

## 1.0.5 - 2023-08-10
### Added
- Added Image Generation Settings: Choose between different Image Generation providers, such as OpenAI or Stability.AI (Stable Diffusion).
- New Single Image Generation Feature: Easily generate a single image based on selected text. For optimized results, first use the "generate image idea" option with your chosen text. Then, with the generated image prompt, select "generate image on this" to create your final image.

## 1.0.4 - 2023-08-01
### Added
- Added Matrix block support.

### Fixed
- Fixed content generation error when no site exists with the hardcoded id "1". (Thanks to @edbarbe for code contribution).

## 1.0.3 - 2023-07-12
### Fixed
- Fixed composer.json file to include the correct version of the plugin for auto-releases.

## 1.0.2 - 2023-07-11

### Added
- Added gpt-4 to the selectable models in API settings.
- Added support for Craft's 'title' field (Thanks to @nitech).

### Fixed
- Fixed issue with overlapping field character counter (Thanks to @nitech).
- Fixed issue with Craft Commerce compatibility, specifically with categories (Thanks to @nitech).
- Fixed issue with translations dropdown showing duplicate languages.

### Changed
- Translation dropdown now shows current site language at the top of the dropdown for convenience.

## 1.0.1 - 2023-07-06
### Fixed
- Fixed issue with new prompt templates not being saved properly.
- Fixed issue with Craft Commerce compatibility, specifically with product entries (Thanks to @nitech for reporting this issue).

### Changed
- Modified the context menu to be more compact.
- Translation options are now showing up in a separate dropdown (Thanks to @nitech for suggestion).

## 1.0.0 - 2023-06-19
### Added
- Initial release
