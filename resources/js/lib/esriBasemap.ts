/**
 * Shared satellite basemap configuration for every Leaflet farm map.
 * Esri World Imagery is a display layer only; farm coordinates remain sourced
 * from the authorized Laravel props and are never sent to Esri by this module.
 */
export const ESRI_WORLD_IMAGERY_URL =
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';

export const ESRI_WORLD_IMAGERY_ATTRIBUTION =
    'Tiles &copy; <a href="https://www.esri.com/" target="_blank" rel="noopener noreferrer">Esri</a> &mdash; Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community';
