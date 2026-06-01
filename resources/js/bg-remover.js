import { removeBackground } from '@imgly/background-removal';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('bgRemover', () => ({
        processing: false,
        progress: 0,
        progressText: '',
        result: null,
        quality: 'auto',
        isPremium: false,

        init() {
            this.isPremium = this.$el.dataset.premium === 'true';
        },

        get hasResult() {
            return this.result !== null;
        },

        async removeBg() {
            const card = document.querySelector('[data-image-info]');

            if (!card) {
                if (window.Livewire) {
                    window.Livewire.dispatch('toast', {
                        message: 'Please choose one image first.',
                        type: 'error',
                    });
                }

                return;
            }

            let img = null;

            try {
                img = JSON.parse(card.dataset.imageInfo);
            } catch {
                if (window.Livewire) {
                    window.Livewire.dispatch('toast', {
                        message: 'Image data could not be loaded. Please choose the image again.',
                        type: 'error',
                    });
                }

                return;
            }

            if (!img?.url) {
                if (window.Livewire) {
                    window.Livewire.dispatch('toast', {
                        message: 'Image preview URL is missing. Please choose the image again.',
                        type: 'error',
                    });
                }

                return;
            }

            this.processing = true;
            this.progress = 0;
            this.progressText = 'Initializing...';
            this.resetResult(false);

            try {
                this.progressText = 'Loading image...';
                this.progress = 8;

                const response = await fetch(img.url);

                if (!response.ok) {
                    throw new Error(`Failed to load image. HTTP ${response.status}`);
                }

                const imageBlob = await response.blob();

                if (!imageBlob || !imageBlob.size) {
                    throw new Error('Invalid image file.');
                }

                this.progressText = 'Checking browser performance...';
                this.progress = 15;

                const hasWebGpu = typeof navigator !== 'undefined' && 'gpu' in navigator;

                let useModel = 'isnet_quint8';
                let useDevice = 'cpu';

                if (this.quality === 'best') {
                    useModel = 'isnet';
                    useDevice = hasWebGpu ? 'gpu' : 'cpu';
                } else if (this.quality === 'balanced') {
                    useModel = hasWebGpu ? 'isnet_fp16' : 'isnet_quint8';
                    useDevice = hasWebGpu ? 'gpu' : 'cpu';
                } else if (this.quality === 'fast') {
                    useModel = 'isnet_quint8';
                    useDevice = 'cpu';
                } else {
                    useModel = hasWebGpu ? 'isnet_fp16' : 'isnet_quint8';
                    useDevice = hasWebGpu ? 'gpu' : 'cpu';
                }

                this.progressText = useDevice === 'gpu'
                    ? 'Processing with GPU...'
                    : 'Processing with CPU...';
                this.progress = 25;

                const resultBlob = await removeBackground(imageBlob, {
                    model: useModel,
                    device: useDevice,
                    output: {
                        format: 'image/png',
                    },
                    progress: (stage, current, total) => {
                        if (stage === 'download') {
                            if (typeof current === 'number' && typeof total === 'number' && total > 0) {
                                this.progress = Math.min(45, 25 + Math.round((current / total) * 20));
                                this.progressText = `Downloading AI model... ${Math.round((current / total) * 100)}%`;
                            } else {
                                this.progressText = 'Downloading AI model...';
                            }
                        }

                        if (stage === 'load') {
                            this.progress = Math.max(this.progress, 50);
                            this.progressText = 'Loading AI model...';
                        }

                        if (stage === 'inference') {
                            if (typeof current === 'number' && typeof total === 'number' && total > 0) {
                                this.progress = 55 + Math.round((current / total) * 40);
                            } else {
                                this.progress = Math.max(this.progress, 70);
                            }

                            this.progressText = useDevice === 'gpu'
                                ? 'Removing background with GPU...'
                                : 'Removing background...';
                        }
                    },
                });

                if (!resultBlob || !(resultBlob instanceof Blob)) {
                    throw new Error('Invalid result from background removal.');
                }

                const processedUrl = URL.createObjectURL(resultBlob);

                this.result = {
                    status: 'success',
                    originalUrl: img.url,
                    processedUrl,
                    processedBlob: resultBlob,
                    originalName: img.name,
                    originalSize: img.size,
                    resultSize: resultBlob.size,
                };

                this.progress = 100;
                this.progressText = 'Completed!';

                if (window.Livewire) {
                    window.Livewire.dispatch('toast', {
                        message: 'Background removed successfully!',
                        type: 'success',
                    });

                    window.Livewire.dispatch('usage-track', {
                        count: 1,
                    });
                }

                if (this.isPremium) {
                    this.backupResult();
                }
            } catch (e) {
                console.error('Background removal failed:', e);

                this.result = {
                    status: 'error',
                    originalUrl: img.url,
                    originalName: img.name || 'Selected image',
                    originalSize: img.size || 0,
                    note: e?.message || 'Processing failed.',
                };

                if (window.Livewire) {
                    window.Livewire.dispatch('toast', {
                        message: 'Background removal failed. Please try a smaller or clearer image.',
                        type: 'error',
                    });
                }
            } finally {
                this.processing = false;
            }
        },

        async backupResult() {
            if (!this.result || this.result.status !== 'success') return;

            const blob = this.result.processedBlob;
            const cleanName = this.result.originalName
                ? this.result.originalName.replace(/\.[^.]+$/, '')
                : 'image';

            const formData = new FormData();
            formData.append('image', blob, `${cleanName}_bg_removed.png`);
            formData.append('original_name', this.result.originalName);
            formData.append('original_size', this.result.originalSize);

            try {
                await fetch('/bg-removed-images', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                    },
                    body: formData,
                });
            } catch {
                // Silent fail — backup is non-critical
            }
        },

        download() {
            if (!this.result || this.result.status !== 'success') return;

            const a = document.createElement('a');
            a.href = this.result.processedUrl;

            const cleanName = this.result.originalName
                ? this.result.originalName.replace(/\.[^.]+$/, '')
                : 'image';

            a.download = `${cleanName}_bg_removed.png`;

            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        },

        resetResult(revoke = true) {
            if (revoke && this.result?.processedUrl) {
                URL.revokeObjectURL(this.result.processedUrl);
            }

            this.result = null;
            this.progress = 0;
            this.progressText = '';
        },

        formatBytes(bytes) {
            if (!bytes || Number.isNaN(bytes)) return '0 B';

            if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            }

            if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            }

            return bytes + ' B';
        },
    }));
});