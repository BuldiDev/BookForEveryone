/**
 * Controller Stimulus per la protezione CSRF tramite cookie di doppio invio.
 *
 * Implementa lo schema "double-submit cookie" come definito da SameOriginCsrfTokenManager di Symfony:
 * 1. Al submit del form, genera un token casuale e lo salva sia nel campo hidden che in un cookie.
 * 2. Con Turbo, invia il token anche come header HTTP per una verifica lato server più robusta.
 * 3. Al termine del submit Turbo, rimuove il cookie CSRF per prevenire il riuso del token.
 *
 * Nota: usare form.requestSubmit() (non form.submit()) per attivare correttamente questo listener.
 */

// Espressioni regolari per validare il formato del nome cookie e del token generato
const nameCheck  = /^[-_a-zA-Z0-9]{4,22}$/;
const tokenCheck = /^[-_/+a-zA-Z0-9]{24,}$/;

// Intercetta il submit nativo del form per iniettare il token CSRF prima dell'invio
document.addEventListener('submit', function (event) {
    generateCsrfToken(event.target);
}, true);

// Con Turbo, aggiunge il token CSRF anche negli header della richiesta fetch
// (richiede framework.csrf_protection.check_header: true in config/packages/framework.yaml)
document.addEventListener('turbo:submit-start', function (event) {
    const h = generateCsrfHeaders(event.detail.formSubmission.formElement);
    Object.keys(h).map(function (k) {
        event.detail.formSubmission.fetchRequest.headers[k] = h[k];
    });
});

// Dopo il submit Turbo, cancella il cookie CSRF per invalidare il token usato
document.addEventListener('turbo:submit-end', function (event) {
    removeCsrfToken(event.detail.formSubmission.formElement);
});

/**
 * Genera un token CSRF casuale con Web Crypto API e lo scrive nel campo hidden e in un cookie.
 * Se il campo non è presente o il cookie è già stato impostato, non fa nulla.
 */
export function generateCsrfToken (formElement) {
    const csrfField = formElement.querySelector('input[data-controller="csrf-protection"], input[name="_csrf_token"]');

    if (!csrfField) {
        return;
    }

    let csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');
    let csrfToken  = csrfField.value;

    if (!csrfCookie && nameCheck.test(csrfToken)) {
        // Salva il nome del cookie originale e sostituisce il valore del campo con un token casuale
        csrfField.setAttribute('data-csrf-protection-cookie-value', csrfCookie = csrfToken);
        csrfField.defaultValue = csrfToken = btoa(String.fromCharCode.apply(null, (window.crypto || window.msCrypto).getRandomValues(new Uint8Array(18))));
    }
    csrfField.dispatchEvent(new Event('change', { bubbles: true }));

    if (csrfCookie && tokenCheck.test(csrfToken)) {
        // Il prefisso __Host- viene aggiunto su HTTPS per rafforzare la protezione SameSite
        const cookie = csrfCookie + '_' + csrfToken + '=' + csrfCookie + '; path=/; samesite=strict';
        document.cookie = window.location.protocol === 'https:' ? '__Host-' + cookie + '; secure' : cookie;
    }
}

/**
 * Costruisce gli header HTTP da aggiungere alla richiesta Turbo.
 * Restituisce un oggetto vuoto se il campo CSRF non è presente o il token non è valido.
 */
export function generateCsrfHeaders (formElement) {
    const headers   = {};
    const csrfField = formElement.querySelector('input[data-controller="csrf-protection"], input[name="_csrf_token"]');

    if (!csrfField) {
        return headers;
    }

    const csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');

    if (tokenCheck.test(csrfField.value) && nameCheck.test(csrfCookie)) {
        headers[csrfCookie] = csrfField.value;
    }

    return headers;
}

/**
 * Scade il cookie CSRF impostando max-age=0 per evitare il riuso del token.
 */
export function removeCsrfToken (formElement) {
    const csrfField = formElement.querySelector('input[data-controller="csrf-protection"], input[name="_csrf_token"]');

    if (!csrfField) {
        return;
    }

    const csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');

    if (tokenCheck.test(csrfField.value) && nameCheck.test(csrfCookie)) {
        const cookie = csrfCookie + '_' + csrfField.value + '=0; path=/; samesite=strict; max-age=0';
        document.cookie = window.location.protocol === 'https:' ? '__Host-' + cookie + '; secure' : cookie;
    }
}

/* stimulusFetch: 'lazy' — questo controller viene caricato solo quando necessario */
export default 'csrf-protection-controller';
