# Changelog

## 1.0.1

- Removed automatic SiteGround Optimizer cache invalidation, which could clear available ACF choices whenever a post was saved.
- Added `withwine_acf_integration_clear_cache()` as a public helper for clearing all choices or an individual source cache on demand.
- Documented custom and third-party cache integration.

## 1.0.0

- Initial public release.
- Populate ACF Select, Radio Button and Checkbox fields from WithWine Products.
- Populate ACF Select, Radio Button and Checkbox fields from WithWine Product Lists.
- Automatic transient caching.
- Admin page for configuring field mappings.
- Manual cache refresh.