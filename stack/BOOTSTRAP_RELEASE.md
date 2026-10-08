# New-site Stack bootstrap release

Platform candidate: `2026.10.08-fleet-readiness-r3`; plugin input bundle: `2026.10.07-stack-gap-bootstrap-r1`. Scope: complete new-site installation inputs for the owner test. Existing-site deployment and the advanced deployment transport remain separate.

The Stack profile selects **33 standard plugins**. The plain profile selects **32**, excluding only Reusable Block Library. Community versions are pinned in `plugins.txt`; every plugin archive is checksum-bound in `manifests/bootstrap-packages.lock.json`. The MRN platform lock separately covers the parent/child pair, MU components and shared runtime.

## Standard plugins

| Plugin | Version | Profile |
| --- | --- | --- |
| Admin Menu Editor Pro (`admin-menu-editor-pro`) | 2.34 | all |
| Advanced Custom Fields PRO (`advanced-custom-fields-pro`) | 6.8.7 | all |
| AME Branding Add-on (`ame-branding-add-on`) | 1.3.13 | all |
| Classic Editor (`classic-editor`) | 1.7.0 | all |
| Enable Media Replace (`enable-media-replace`) | 4.2.2 | all |
| Post SMTP (`post-smtp`) | 4.0.2 | all |
| HappyFiles Pro (`happyfiles-pro`) | 1.9.1 | all |
| MRN ACF Focal Point (`mrn-acf-focal-point`) | 1.1.2 | all |
| MRN Announcements (`mrn-announcements`) | 1.8.2 | all |
| MRN Comment Management (`mrn-comment-management`) | 1.3.0 | all |
| MRN Font Awesome Profile Manager (`mrn-fontawesome-profile-manager`) | 0.5.1 | all |
| MRN Google Fonts (`mrn-google-fonts`) | 1.0.7 | all |
| MRN Config Helper (`mrn-config-helper`) | 0.1.71 | all |
| MRN Media Tools (`mrn-media-bulk-tools`) | 0.13.1 | all |
| MRN Mega Menu (`mrn-mega-menu`) | 0.17.2 | all |
| MRN reCAPTCHA Enterprise Manager (`mrn-recaptcha-enterprise-manager`) | 0.1.4 | all |
| MRN Stack Deployment Agent (`mrn-stack-deployment-agent`) | 0.3.2 | all |
| MRN Template Inspector (`mrn-template-inspector`) | 0.2.7 | all |
| MRN Reusable Block Library (`mrn-reusable-block-library`) | 0.2.0 | stack |
| MRN Universal Sticky Bar (`mrn-universal-sticky-bar`) | 1.1.10 | all |
| Post Duplicator (`post-duplicator`) | 3.0.16 | all |
| Post Types Order (`post-types-order`) | 2.5.6 | all |
| Relevanssi (`relevanssi`) | 4.28.4 | all |
| Relevanssi Live Ajax Search (`relevanssi-live-ajax-search`) | 2.6 | all |
| Advanced Editor Tools (`tinymce-advanced`) | 5.10.1 | all |
| UpdraftPlus - Backup/Restore (`updraftplus`) | 2.26.6 | all |
| AME Toolbar Editor (`wp-toolbar-editor`) | 1.5.2 | all |
| WPForms (`wpforms`) | 1.10.2.1 | all |
| SEOPress (`wp-seopress`) | 10.3 | all |
| SEOPress PRO (`wp-seopress-pro`) | 10.3 | all |
| MRN Hierarchical Menu Taxonomies (`mrn-hierarchical-menu-taxonomies`) | 0.1.1 | all |
| MRN Layout Import/Export (`mrn-layout-import-export`) | 0.1.2 | all |
| MRN Tokens (`mrn-tokens`) | 0.1.4 | all |

## Environment and ownership

- Activate the MRN Base Stack Child 1.1.0 over parent 1.5.8. Child content remains site-owned.
- Keep SEOPress Free/PRO 10.3 active for native editing. Development disables indexing, tracking and external jobs.
- Post SMTP 4.0.2 is installed and inactive in development. Provider configuration and verified delivery belong to launch.
- reCAPTCHA uses qualified 0.1.4. The 0.2.2 Gloves pilot source is accepted, but generic native-controller qualification and broader adoption remain gated.
- The MRN MU loader supplies the 12 documented platform components plus wrapper/shared support.
- Premium licenses, reCAPTCHA/UptimeRobot credentials and backup destination settings stay in the existing secure manager configuration; no credentials are shipped in the release archive.
- Development scheduled backups remain manual. Shared-runtime writes still require the established fresh remote database-backup gate.

## Excluded defaults

SmartCrawl, SEO Helper, ACF Character Count, AI Assist, Editor Enhancements, MRN Cookie Consent, MRN GTM Injector, FluentSMTP, SendGrid Provisioning and Background Video Pop-Out Disabler are excluded. Prior source and settings remain recoverable; this release does not uninstall anything on existing sites. See [recovery procedure](RESTORING-RETIRED-CAPABILITIES.md).

## Acceptance and handoff

Source/package and isolated-runtime qualification prepare the release for the owner's new-site test. Provider onboarding, real license/credential checks, remote backup storage and real external service delivery must be read back on that new site. Passing fixture tests is not proof of these provider operations.

[Deployment task handoff](../docs/STACK-DEPLOYMENT-HANDOFF.md) defines the next work after the owner test. Do not expand this bootstrap release into a deployment-system rewrite.
