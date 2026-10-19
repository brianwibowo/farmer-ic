export type SpeechRecognitionEventLike = Event & {
    results: ArrayLike<ArrayLike<{ transcript: string }>>;
};

export type SpeechRecognitionErrorEventLike = Event & {
    error?: string;
};

export type SpeechRecognitionLike = {
    lang: string;
    interimResults: boolean;
    continuous: boolean;
    onresult: ((event: SpeechRecognitionEventLike) => void) | null;
    onerror: ((event: SpeechRecognitionErrorEventLike) => void) | null;
    onend: (() => void) | null;
    start: () => void;
    stop: () => void;
    abort?: () => void;
};

export type SpeechRecognitionConstructor = new () => SpeechRecognitionLike;

export type VoiceResponse = {
    transcript?: string;
    text?: string;
    message?: string;
};

export function voiceHeaders(): Record<string, string> {
    const element = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    );
    const metaToken = element?.content;
    if (metaToken) {
        return {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': metaToken,
        };
    }

    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='));
    let token: string | null = null;
    if (cookie) {
        try {
            token = decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
        } catch {
            token = null;
        }
    }
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
    };
}

export function browserRecognitionError(code: string | undefined): string {
    switch (code) {
        case 'not-allowed':
        case 'service-not-allowed':
            return 'Izin mikrofon ditolak. Izinkan mikrofon di pengaturan browser, lalu coba lagi.';
        case 'audio-capture':
            return 'Mikrofon tidak ditemukan atau sedang dipakai aplikasi lain.';
        case 'no-speech':
            return 'Tidak terdengar suara. Coba bicara lebih dekat ke mikrofon.';
        case 'network':
            return 'Dikte browser membutuhkan koneksi internet. Coba lagi atau ketik pesan.';
        case 'language-not-supported':
            return 'Dikte bahasa Indonesia belum didukung browser ini.';
        case 'aborted':
            return '';
        default:
            return 'Pengenalan suara di browser gagal. Coba bicara lagi.';
    }
}

export function transcribeErrorMessage(
    status: number,
    payload: unknown,
): string {
    const payloadMessage =
        typeof payload === 'object' && payload !== null && 'message' in payload
            ? (payload as { message?: unknown }).message
            : null;
    const serverMessage =
        typeof payloadMessage === 'string' ? payloadMessage : '';
    if (status === 413)
        return 'Rekaman terlalu besar. Coba durasi lebih pendek.';
    if (status === 429)
        return 'Layanan transkripsi sedang sibuk. Coba lagi sebentar.';
    if (status >= 500)
        return 'Layanan transkripsi sedang tidak tersedia. Coba lagi nanti.';
    return serverMessage || 'Transkrip suara belum tersedia.';
}

export function audioFilename(blob: Blob): string {
    const mime = blob.type.split(';', 1)[0];
    const extension =
        {
            'audio/mpeg': 'mp3',
            'audio/mp4': 'mp4',
            'audio/ogg': 'ogg',
            'audio/wav': 'wav',
            'audio/x-wav': 'wav',
            'audio/x-m4a': 'm4a',
            'audio/webm': 'webm',
        }[mime] ?? 'webm';
    return `voice-message.${extension}`;
}
