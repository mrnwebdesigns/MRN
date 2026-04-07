MRN PROJECT CONTEXT

This is a WordPress-based website building platform.

Key facts:
- Uses Classic Editor (NOT Gutenberg)
- Built as a modular system using:
  - stack theme (mrn-base-stack)
  - plugins + MU plugins
  - shared runtime code
- Many features are interconnected across admin, frontend, and APIs

Core architecture rules:
- Theme = layout, rendering, builder system
- Plugins = features, integrations, admin behavior
- MU plugins = shared runtime and cross-cutting logic
- Site Styles = design tokens (colors, accents)
- Config Helper = global settings + social data

Critical constraints:
- Do NOT break existing builder behavior
- Do NOT break shared theme hooks (CSS classes, variables, data attributes)
- Always assume changes can affect multiple system areas
- Prefer existing helpers/contracts over new logic

Working approach:
- Only use relevant context for the current task
- Do NOT assume full system memory is loaded
- Always identify:
  1. what part of the system is being changed
  2. what dependencies might be affected
  3. the minimal safe change

If unclear:
- ask before making large or cross-system changes