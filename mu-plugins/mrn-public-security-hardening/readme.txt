=== MRN Public Security Hardening ===
Contributors: mrnwebdesigns
Stable tag: 0.4.2
Tested up to: 7.1

Shared MRN must-use plugin for public security policy and configurable login routing.

== Description ==

Distributed through the shared MRN Stack loader. See README.md for configuration,
compatibility, regression tests and the component QA workflow.

== Changelog ==

= 0.4.2 =
* Resolve relative WordPress login redirects to the configured custom login URL.
* Preserve query arguments and default endpoint protection for root and subdirectory installs.
* Add actual WordPress HTTP coverage for reset, login, logout, registration and reauthentication.
* Add a main landmark to the custom login screen while preserving existing markup and roles.
