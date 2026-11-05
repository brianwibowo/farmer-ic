/**
 * Shared satellite basemap configuration for every Leaflet farm map.
 * Farm coordinates come from authorized Laravel props. Leaflet requests Esri
 * tiles for the visible map area; Esri is only the satellite basemap provider.
 */
export const ESRI_WORLD_IMAGERY_URL =
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';

export const ESRI_WORLD_IMAGERY_ATTRIBUTION =
    'Tiles &copy; <a href="https://www.esri.com/" target="_blank" rel="noopener noreferrer">Esri</a> &mdash; Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community';
