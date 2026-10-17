# Farmer IC Design System

This document translates the **Design System** canvas in the [UI Design Kebon Figma file](https://www.figma.com/design/WEyyR9lHsrkUD1x4WEi3H6/UI-Design-Kebon?node-id=3-4) into implementation guidance for the Farmer IC prototype.

Use this as the shared visual reference for new screens and components. Keep the existing project theme utilities as the implementation layer; the hex values below describe the design tokens and should not be repeated as hard-coded page classes.

## Foundations

### Corner radius

- Default rounded corner: **12px**
- Use the same radius for cards, buttons, fields, popups, and other contained surfaces unless a component specification says otherwise.

### Color palette

| Token      | Hex       | Intended use                                          |
| ---------- | --------- | ----------------------------------------------------- |
| Primary    | `#68BB6C` | Main actions, selected controls, active navigation    |
| Secondary  | `#1C5E21` | Strong emphasis, pressed or high-contrast actions     |
| Accent     | `#93A99F` | Supporting accents and secondary visual cues          |
| Background | `#F9FAF9` | App and page background                               |
| Surface    | `#ABE7B2` | Filled cards, highlighted surfaces, soft emphasis     |
| Foreground | `#070807` | Primary text and high-contrast content                |
| Text Muted | `#2A1A0E` | Secondary text, descriptions, metadata                |
| Border     | `#D9D9D9` | Dividers, outlines, field borders                     |
| Success    | `#4A943E` | Successful actions and positive status                |
| Warning    | `#EDC92C` | Caution and attention states                          |
| Error      | `#BC0202` | Validation errors and destructive feedback            |
| Info       | `#D9D9D9` | Informational status when no stronger color is needed |
| Disable    | `#E5E7EA` | Disabled backgrounds and controls                     |

In Vue components, use the project semantic utilities such as `text-primary`, `text-foreground`, `text-muted-foreground`, `bg-card`, `bg-muted`, `border-border`, `text-success`, `text-warning`, and `text-error`. Add or adjust theme tokens centrally when a token is missing.

## Typography

The Figma typography scale uses the following size, line-height, and weight combinations. The design system records spacing as zero for these styles.

### Headings

| Style       | Weight    | Size | Line height |
| ----------- | --------- | ---: | ----------: |
| H1 Headline | Semi Bold | 32px |        40px |
| H2 Headline | Semi Bold | 28px |        36px |
| H3 Headline | Semi Bold | 24px |        32px |
| H4 Headline | Semi Bold | 20px |        28px |
| H5 Headline | Semi Bold | 18px |        26px |

### Supporting text

| Style       | Weight    | Size | Line height |
| ----------- | --------- | ---: | ----------: |
| S1 Subtitle | Semi Bold | 18px |        28px |
| S2 Subtitle | Semi Bold | 16px |        24px |
| B1 Body     | Regular   | 16px |        24px |
| B2 Body     | Medium    | 16px |        24px |
| B3 Body     | Regular   | 14px |        20px |
| B4 Body     | Medium    | 14px |        20px |
| C1 Caption  | Regular   | 12px |        16px |
| C2 Caption  | Medium    | 12px |        16px |
| C3 Caption  | Medium    | 10px |        14px |
| Label       | Medium    | 12px |        16px |

### Button text sizes

| Size   | Weight    | Font size | Line height |
| ------ | --------- | --------: | ----------: |
| Giant  | Semi Bold |      18px |        24px |
| Large  | Semi Bold |      16px |        20px |
| Medium | Semi Bold |      14px |        16px |
| Small  | Semi Bold |      12px |        16px |
| Tiny   | Semi Bold |      10px |        12px |

Use real heading elements and labels in the UI. Keep text wrapping responsive and avoid fixed widths that force truncation on small phones.

## Button

The button system supports five sizes, three visual styles, four interaction states, and text/icon content combinations.

### Sizes

| Size   | Target height |
| ------ | ------------: |
| Giant  |          56px |
| Large  |          48px |
| Medium |          40px |
| Small  |          32px |
| Tiny   |          24px |

Width should hug the content with consistent horizontal padding. Use the 12px radius and keep icon and label alignment centered.

### Styles and states

- **Filled:** primary green surface for the main action.
- **Outline:** transparent or surface background with a visible border.
- **Clear:** no container emphasis; use for low-priority actions.
- **Default:** primary action color.
- **Hover:** darker green emphasis.
- **Focus:** visible focus ring around the control.
- **Press:** strongest green/secondary emphasis while pressed.
- **Disabled:** `Disable` background and muted foreground; no hover or press treatment.

Buttons may contain text, an icon, or an icon with text. When text and icons are combined, support leading and trailing icons and preserve a clear gap between them. Use a native `<button>` and expose a visible focus state.

## Input

Inputs are defined in **Large** and **Medium** sizes with **Filled** and **Outline** styles. Each style supports these states:

- Default
- Filled
- Hover
- Focus
- Disabled
- Success
- Info
- Warning
- Error

Every input needs a visible `<label>`, a helpful value or placeholder, and inline validation feedback. Use the semantic status colors for validation and keep the focus ring visible in both light and dark themes.

## Pop-up

Pop-ups support both horizontal and vertical button layouts. Content patterns include:

- Title and message
- Title, message, and input
- Text with horizontal buttons
- Text with vertical buttons

Use a real dialog or modal pattern, keep the action order predictable, and make the close and primary actions keyboard accessible.

## Pagination

Pagination includes text and icon items with these states:

- Default
- Hover
- Focus
- Selected
- Disabled

Use buttons or links for page items, expose the current page with `aria-current="page"`, and provide accessible labels for previous and next icon controls.

## Tabs

Tabs include individual tab items and five-tab examples. Supported states are:

- Default
- Hover
- Focus
- Selected
- Disabled

Use semantic tab markup when switching content in place. Keep the selected indicator visible and allow the tab row to scroll horizontally on narrow screens.

## Bottom navigation

The bottom bar has four menu destinations:

- Beranda
- Kebun
- Keuangan
- Aktivitas

The Figma page includes both full-color and LoFi variants. Use the active state for the current route, provide an accessible label for every icon, and keep the bar usable on standard, Plus, and Pro Max phone widths.

## Implementation rules for Farmer IC

- Keep feature data in frontend seed files and browser storage according to `AGENTS.md`.
- Use Vue 3 `<script setup>` and the Composition API.
- Use semantic theme utilities instead of hard-coded color classes in page and component templates.
- Build reusable controls in `resources/js/components/` and keep page-specific composition in `resources/js/pages/`.
- Include loading, empty, success/error, and validation states for interactive screens.
- Check new screens in light and dark themes and at small, medium, and large phone widths.
- Prefer local assets and existing project primitives; do not introduce a new UI package for these patterns.
