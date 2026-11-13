"""Regression coverage for the shared Layout Library preview contract."""
from pathlib import Path
import unittest


ROOT = Path(__file__).resolve().parents[1]
LIBRARY = (ROOT / "includes/layout-library.php").read_text()
PROFILE = (ROOT / "includes/layout-library/preview-profiles.php").read_text()


class LayoutLibraryPreviewContractTests(unittest.TestCase):
    def test_complex_layouts_have_curated_blueprints(self):
        for layout in ("card", "grid", "logos", "slider", "showcase", "cta", "cta_block", "image_content"):
            self.assertIn("case '%s':" % layout, LIBRARY)
        self.assertIn("card_rows", LIBRARY)
        self.assertIn("slider_items", LIBRARY)
        self.assertIn("showcase_items", LIBRARY)

    def test_duplicate_names_are_written_by_key_paths(self):
        self.assertIn("$path[] = $field_key", LIBRARY)
        self.assertIn("array( $field_key, 1 )", LIBRARY)
        self.assertIn("ACF group fields have no row selector", LIBRARY)
        self.assertNotIn("array( $field_name, 1, $field['name'] )", LIBRARY)

    def test_assets_are_private_and_not_recent_uploads(self):
        self.assertIn("MRN_ACTIVE_STYLE_GUIDE_LAYOUT_PREVIEW_ASSET_META", PROFILE)
        self.assertIn("post_status' => 'private'", PROFILE)
        self.assertIn("'' !== (string) get_post_meta", PROFILE)
        self.assertIn("exclude_layout_preview_assets_from_media_library", PROFILE)
        self.assertNotIn("get_layout_library_sample_attachment_id", LIBRARY)

    def test_generic_generated_and_local_paths_are_supported(self):
        self.assertIn("'kind' => 'generated'", PROFILE)
        self.assertIn("'local' === $kind", PROFILE)
        self.assertIn("add_filter( 'upload_mimes', $svg_mimes )", PROFILE)
        self.assertIn("remove_filter( 'upload_mimes', $svg_mimes )", PROFILE)
        self.assertIn("mrn_active_style_guide_layout_preview_profile", PROFILE)


if __name__ == "__main__":
    unittest.main()
