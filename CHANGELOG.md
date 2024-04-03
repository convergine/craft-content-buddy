# Content Buddy - Changelog

All notable changes to this project will be documented in this file.

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
