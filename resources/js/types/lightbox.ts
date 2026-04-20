export interface LightboxItem {
    id: string | number;
    src: string;
    zoomSrc?: string;
    originalSrc?: string;
    zoomResolution?: string;
    originalResolution?: string;
    srcset?: string;
    alt?: string;
    canZoom?: boolean;
    canHaveTransparency?: boolean;
    [key: string]: unknown;
}
