=== Zeko ===
Contributors: zeko
Requires at least: 5.8
Tested up to: 7.1.2
Requires PHP: 7.4
License: GNU General Public License v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: blog, e-commerce, education, custom-header, custom-logo, custom-menu, featured-images, full-width-template, rtl-language-support, translation-ready

A community-first WordPress theme that powers the Zeko ecosystem: custom login and registration, a role-based profile builder, a dashboard with module tabs, an activity feed, private messaging, and friendships.

== Description ==

Zeko is a feature-rich, community-first WordPress theme designed to host the Zeko ecosystem — jobs, Q&A, learning, mentorship, dating, rewards, freelance, e-commerce, and AI modules.

= Core Features =

* **Custom Auth** — Custom login and registration with activity logging
* **Profile Builder** — Role-based profile fields and completion tracking
* **Dashboard** — Front-end dashboard with widgets and module tabs (`zeko_dashboard_tabs`)
* **Activity Feed** — Activity stream with AJAX infinite scroll and reactions; reads from Zeko Core's cached activity store
* **Messaging** — Private messaging with unread counts
* **Friendships** — Friend request/accept/reject/unfriend flows with notifications
* **Notification Bell** — Global in-app notification aggregation
* **Design Tokens** — CSS custom-property tokens, RTL support, dark-mode-ready palette, `prefers-reduced-motion`
* **Module-Ready** — Every plugin integration is `class_exists`-guarded, so the theme works with zero plugins and degrades gracefully

= Compatible Plugins =

* Zeko Core (shared foundation — recommended)
* Zeko Jobs, Zeko QA, Zeko Learn, Zeko Mentor, Zeko Love, Zeko Freelance, Zeko Shop, Zeko Rewards, Zeko AI, Zeko Pay

== Installation ==

1. Upload the `zeko` folder to `/wp-content/themes/`
2. Activate the theme through the 'Appearance' menu in WordPress
3. Configure theme options under Appearance > Customize (including allowed admin roles)
4. Install and activate the Zeko Core plugin and any Zeko modules you want

== Frequently Asked Questions ==

= Does Zeko work without the Zeko plugins? =

Yes. All module integrations are capability-guarded. Without plugins you still get auth, profiles, activity, messaging, friendships, and a dashboard.

= Where does the activity feed data come from? =

The feed reads from Zeko Core's `Zeko_Core_Activity` store when the plugin is active, and falls back to the theme's own schema otherwise.

= How do I add modules to the dashboard? =

Modules self-register through the `zeko_dashboard_tabs` and `zeko_dashboard_widgets` filters. No theme edits are needed.

== Dependencies ==

The theme works standalone, but the Zeko Core plugin is recommended for the shared activity store and dashboard-tab registry. Each optional plugin remains `class_exists`-guarded.

== Privacy ==

Auth, profile, activity, and messaging records live in the theme's own tables; activity rows store an IP address when a login or action is logged. The theme registers no privacy exporters/erasers itself — data created by the Zeko plugins is exported/erased through those plugins' own privacy tools.

== External Services ==

The theme itself makes no outbound requests. Video calls and embeddings are provided by the Zeko Mentor and Zeko Learn plugins when installed.

== Uninstall ==

Deleting the theme does not remove the activity tables; they are shared with Core and other modules. Remove Zeko Core last (it never drops the shared activity table) or clear the tables manually if you no longer use the ecosystem.

== Troubleshooting ==

* Activity feed empty? Install and activate Zeko Core, or check the feed is loaded via `[zeko_activity_feed]` and its AJAX endpoint.
* Pages not being created? Re-run the theme setup from Appearance > Customize options, then visit Settings > Permalinks and save.

== Screenshots ==

No screenshots are bundled with the theme.

== Changelog ==

= 1.0.0 =
* Initial release
* Custom auth, profile builder, dashboard, activity feed, messaging, friendships
* Design tokens, RTL, dark-mode-ready, reduced-motion support
* Graceful no-plugin mode

== Upgrade Notice ==

= 1.0.0 =
Initial release of the Zeko theme.
