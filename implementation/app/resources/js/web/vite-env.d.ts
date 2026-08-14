/// <reference types="vite/client" />

interface ImportMetaEnv {
    readonly VITE_DEBUG_VERIFICATION_TOKENS?: string;
    readonly VITE_DEMO_EMAIL?: string;
    readonly VITE_DEMO_PASSWORD?: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}
