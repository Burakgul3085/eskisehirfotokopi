// Alpine bileşenleri (CSP uyumlu: şablonda yalnızca bu nesnelerin özellik ve metotları kullanılır).

/** Aç/kapa (menü, açılır panel). */
export function toggle() {
    return {
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
        isOpen() {
            return this.open;
        },
        isClosed() {
            return !this.open;
        },
        /** Yan menü: açıkken ekrana kayar. */
        slideInClass() {
            return this.open ? 'translate-x-0' : '';
        },
    };
}

/** Parola alanında göster/gizle. */
export function passwordField() {
    return {
        visible: false,
        toggleVisible() {
            this.visible = !this.visible;
        },
        inputType() {
            return this.visible ? 'text' : 'password';
        },
        label() {
            return this.visible ? 'Gizle' : 'Göster';
        },
    };
}

/** Bildirim (flash) mesajı: birkaç saniye sonra kendiliğinden kapanır. */
export function flash() {
    return {
        shown: true,
        init() {
            setTimeout(() => {
                this.shown = false;
            }, 6000);
        },
        dismiss() {
            this.shown = false;
        },
    };
}

/** Renk seçici ile metin alanını eşler (yönetim → görünüm). */
export function colorField() {
    return {
        value: '',
        init() {
            this.value = this.$el.dataset.value || '#000000';
        },
        sync(event) {
            this.value = event.target.value;
        },
    };
}

/** Seçilen görselin önizlemesi (logo, favicon). */
export function imagePreview() {
    return {
        url: '',
        init() {
            this.url = this.$el.dataset.current || '';
        },
        pick(event) {
            const file = event.target.files?.[0];
            if (file) {
                this.url = URL.createObjectURL(file);
            }
        },
        hasImage() {
            return this.url !== '';
        },
        noImage() {
            return this.url === '';
        },
    };
}
