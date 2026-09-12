# DokuWiki Media Autoscale Plugin

`mediaautoscale` normalizes oversized JPEG and PNG uploads before DokuWiki stores the final media file.

## Keep it simple

The goal of this plugin is deliberately simple: **upload an image to the wiki and do not worry about the secondary details**.

It is aimed at users who want predictable, sensible media handling without having to decide image dimensions, compression levels or quality settings for every upload. The plugin therefore follows one consistent output policy instead of exposing unnecessary tuning options.

Image dimensions are kept suitable for normal wiki layouts, while the actual displayed size remains the responsibility of the DokuWiki template and its CSS. JPEG output uses one consistent quality target. This keeps the workflow simple and produces uniform results across uploads.

In short: choose the image, upload it, and let the wiki handle the rest.

## Important: configure before enabling

The plugin intentionally ships with **no default ImageMagick path and no default working directory**. Configure both values before enabling it.

Add values to your DokuWiki local configuration, for example:

```php
$conf['plugin']['mediaautoscale']['convert'] = '/usr/bin/convert-im6';
$conf['plugin']['mediaautoscale']['workdir'] = '/path/to/mediaautoscale-work';
```

Requirements for the working directory:

- it must already exist;
- it must be writable by the web-server user;
- it must be on the **same filesystem as DokuWiki media storage**, because the normalized file is handed back to DokuWiki using `rename()`.

The `convert` setting must be an absolute path to an executable ImageMagick `convert` binary. The initial implementation was tested with ImageMagick 6 (`/usr/bin/convert-im6`).

If the plugin is enabled without valid settings, supported image uploads are rejected with an explicit configuration error rather than being stored unprocessed.

## Default image policy

- JPEG and PNG: maximum 1440 px on the longest edge
- maximum final stored size: 2 MiB
- JPEG quality: 90
- maximum decoded image size: 64 megapixels
- JPEG EXIF orientation is applied before storing
- metadata is stripped from processed images
- PNG remains PNG and transparency is preserved
- GIF and ICO are not resized; files larger than 2 MiB are rejected
- images already within both the dimension and size limits are stored unchanged

If a JPEG or PNG still exceeds 2 MiB at 1440 px, dimensions are reduced iteratively while JPEG quality remains at 90.

## Requirements

- DokuWiki action plugin support with `MEDIA_UPLOAD_FINISH`
- PHP `exec()` enabled
- ImageMagick with JPEG and PNG support
- writable work directory as described above

## Compatibility

The author currently runs and develops the plugin on **DokuWiki 2018-04-22b "Greebo"**, where it has been tested end-to-end through the Media Manager.

Source-level review of the DokuWiki APIs used by the plugin indicates compatibility through **DokuWiki 2026-07-14c "Mort"**. In particular, `MEDIA_UPLOAD_FINISH` retains the event data used by this plugin, and current DokuWiki still provides legacy aliases for `DokuWiki_Action_Plugin`, `Doku_Event_Handler` and `Doku_Event`.

Therefore:

- **tested DokuWiki version:** Greebo (2018-04-22b)
- **expected compatible through:** Mort (2026-07-14c)
- newer releases between Greebo and Mort have not all been tested individually

The plugin is intentionally written using PHP syntax compatible with **PHP 5.6**. It has been tested on **PHP 5.6.40**. Source review indicates no language-level incompatibility with current PHP 7.x or 8.x releases, including PHP 8.5, but these versions have not yet been systematically runtime-tested.

Therefore:

- **minimum PHP:** 5.6
- **tested PHP:** 5.6.40
- **expected compatible through:** PHP 8.5

Initial development and end-to-end testing used:

- DokuWiki 2018-04-22b "Greebo"
- PHP 5.6.40
- ImageMagick 6.8.9-9

## How it works

The plugin registers a `BEFORE` handler for `MEDIA_UPLOAD_FINISH`. Oversized JPEG/PNG files are converted into a separate work file, validated, and then supplied back to DokuWiki. DokuWiki continues its normal media-save, revision and changelog workflow.

The original upload is not overwritten while conversion is in progress.

## Tested examples

- ~13 MiB JPEG -> 1080x1440, JPEG quality 90, ~573 KiB
- JPEG upload -> 1440x1440, ~397 KiB
- PNG upload -> 1440x984, ~1.65 MiB
- already compliant JPEG -> stored without re-encoding

## Source and issues

Source: https://github.com/tomasgal/mediaautoscale

Issues: https://github.com/tomasgal/mediaautoscale/issues
