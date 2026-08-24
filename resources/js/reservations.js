import { Modal } from 'bootstrap';

const POLL_INTERVAL_MS = 1500;
const POLL_TIMEOUT_MS = 60000;

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const payloadFrom = (form) => {
    const data = Object.fromEntries(new FormData(form));
    delete data._token;

    return data;
};

const showAlert = (container, variant, text) => {
    const alert = document.createElement('div');
    alert.className = `alert alert-${variant}`;
    alert.setAttribute('role', 'alert');
    alert.textContent = text;
    container.replaceChildren(alert);
    container.classList.remove('d-none');
};

const clearFieldErrors = (form) => {
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    form.querySelectorAll('[data-reservation-error]').forEach((el) => {
        el.textContent = '';
    });
};

const renderFieldErrors = (form, errors) => {
    Object.entries(errors).forEach(([field, messages]) => {
        const input = form.querySelector(`[name="${field}"]`);
        const feedback = form.querySelector(`[data-reservation-error="${field}"]`);

        if (feedback) {
            feedback.textContent = messages.join(' ');
        }

        input?.classList.add('is-invalid');
    });
};

class ReservationFormController {
    constructor(form) {
        this.form = form;
        this.submitButton = form.querySelector('[data-reservation-submit]');
        this.spinner = document.querySelector('[data-reservation-spinner]');
        this.result = document.querySelector('[data-reservation-result]');
        this.modalElement = document.querySelector('[data-reservation-modal]');
        this.modalTitle = this.modalElement?.querySelector('[data-reservation-modal-title]');
        this.modalBody = this.modalElement?.querySelector('[data-reservation-modal-body]');
        this.modal = this.modalElement ? Modal.getOrCreateInstance(this.modalElement) : null;
        form.addEventListener('submit', (event) => this.#onSubmit(event));
    }

    async #onSubmit(event) {
        event.preventDefault();
        this.#reset();

        try {
            const response = await fetch(this.form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(payloadFrom(this.form)),
            });

            if (response.status === 422) {
                const { message, errors } = await response.json();
                this.#showValidationErrors(message, errors ?? {});

                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const { attempt_url: attemptUrl } = await response.json();
            await this.#pollAttempt(attemptUrl);
        } catch {
            this.#showOutcome(
                'danger',
                'No se pudo procesar la solicitud. Verificá tu conexión e intentá nuevamente.',
            );
        } finally {
            this.#setBusy(false);
        }
    }

    async #pollAttempt(attemptUrl) {
        this.#setBusy(true);
        const startedAt = Date.now();

        while (Date.now() - startedAt < POLL_TIMEOUT_MS) {
            const response = await fetch(attemptUrl, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const { status, result, error } = await response.json();

            if (status === 'confirmed') {
                this.#showConfirmation(result);

                return;
            }

            if (status === 'rejected' || status === 'failed') {
                this.#showOutcome(
                    'danger',
                    error || 'La reserva fue rechazada. No quedó registrada.',
                    'No pudimos registrar tu reserva',
                );

                return;
            }

            await delay(POLL_INTERVAL_MS);
        }

        this.#showOutcome(
            'warning',
            'La reserva sigue procesándose en segundo plano. Podés cerrar este aviso e intentar nuevamente en unos momentos.',
            'La reserva sigue procesándose',
        );
    }

    #reset() {
        clearFieldErrors(this.form);
        this.result.replaceChildren();
        this.result.classList.add('d-none');
    }

    #setBusy(busy) {
        this.spinner?.classList.toggle('d-none', !busy);
        if (this.submitButton) this.submitButton.disabled = busy;
    }

    #showModal(title, body, tone) {
        if (!this.modal) {
            return;
        }

        if (this.modalTitle) {
            this.modalTitle.classList.remove('text-success', 'text-danger', 'text-warning');
            this.modalTitle.classList.add(`text-${tone}`);
            this.modalTitle.textContent = title;
        }

        if (this.modalBody) {
            this.modalBody.textContent = body;
        }

        this.modal.show();
    }

    #showOutcome(tone, body, title) {
        showAlert(this.result, tone, body);

        const titles = {
            success: 'Reserva confirmada',
            danger: 'No pudimos registrar tu reserva',
            warning: 'La reserva sigue procesándose',
        };

        this.#showModal(title ?? titles[tone], body, tone);
    }

    #showValidationErrors(message, errors) {
        renderFieldErrors(this.form, errors);
        this.#showModal(
            'Error de validación',
            message || 'Revisá los datos ingresados.',
            'danger',
        );
    }

    #showConfirmation(result) {
        const tables = Array.isArray(result.table_codes)
            ? ` Mesas asignadas: ${result.table_codes.join(', ')}.`
            : '';
        const schedule = `${String(result.starts_at).slice(11, 16)}–${String(result.ends_at).slice(11, 16)}`;
        const body =
            `Tu reserva quedó registrada en ${result.location_name}: ${result.people_count} persona(s), ` +
            `${schedule}.${tables}`;

        this.#showOutcome('success', body);
    }
}

const initReservationForms = () => {
    document
        .querySelectorAll('[data-reservation-form]')
        .forEach((form) => new ReservationFormController(form));
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReservationForms);
} else {
    initReservationForms();
}
