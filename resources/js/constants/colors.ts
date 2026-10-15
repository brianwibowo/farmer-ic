/**
 * Global color tokens for Farmer IC.
 * Configured in resources/css/app.css and synchronized here for JavaScript/TypeScript usage.
 */
export const APP_COLORS = {
    primary: '#68BB6C',
    secondary: '#1C5E21',
    accent: '#93A99F',
    background: '#F9FAF9',
    surface: '#ABE7B2',
    foreground: '#070807',
    textMuted: '#2A1A0E',
    border: '#D9D9D9',
    success: '#4A943E',
    warning: '#EDC92C',
    error: '#BC0202',
    info: '#D9D9D9',
    disable: '#E5E7EA',
} as const;

export type AppColorKey = keyof typeof APP_COLORS;
export type AppColorHex = (typeof APP_COLORS)[AppColorKey];
