<div
    x-data="{
        sent: false,
        exitHandler: null,
        init() {
            this.exitHandler = () => this.markReadOnExit();
            window.addEventListener('pagehide', this.exitHandler);
            document.addEventListener('livewire:navigating', this.exitHandler);
        },
        markReadOnExit() {
            if (this.sent) return;
            const data = new FormData();
            data.append('_token', @js(csrf_token()));
            data.append('version', @js($notificationVersion));
            const url = @js($readOnExitUrl);
            this.sent = navigator.sendBeacon?.(url, data) === true;
            if (!this.sent) {
                this.sent = true;
                fetch(url, { method: 'POST', credentials: 'same-origin', body: data, keepalive: true }).catch(() => {});
            }
        },
        destroy() {
            window.removeEventListener('pagehide', this.exitHandler);
            document.removeEventListener('livewire:navigating', this.exitHandler);
        }
    }"
>
    <p>خوانده‌شدن این اعلان هنگام خروج از صفحه جزئیات ثبت می‌شود.</p>
</div>
