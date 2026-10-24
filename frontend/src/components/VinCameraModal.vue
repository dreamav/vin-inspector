<template>
  <transition name="modal-fade">
    <div v-if="isOpen" class="modal-backdrop" @click.self="handleClose">
      <div class="modal-container glass-card" role="dialog" aria-modal="true">
        <!-- Заголовок модалки -->
        <div class="modal-header">
          <div class="header-title">
            <span class="header-icon">📷</span>
            <div>
              <h3>Сканирование VIN-кода</h3>
              <p class="header-desc">Сфотографируйте шильдик под стеклом, наклейку на стойке двери или выбитый номер на кузове</p>
            </div>
          </div>
          <button type="button" class="btn-close" @click="handleClose" title="Закрыть">✕</button>
        </div>

        <!-- Тело модалки -->
        <div class="modal-body">
          <!-- Режим 1: Просмотр и снимок с камеры (Webcam / Мобильная камера) -->
          <div v-if="mode === 'camera'" class="camera-view">
            <div class="video-container" :class="{ 'has-error': cameraError }">
              <video 
                ref="video" 
                autoplay 
                playsinline 
                muted 
                class="camera-stream"
                @loadedmetadata="onVideoReady"
              ></video>

              <!-- Направляющая визирная рамка для выравнивания VIN -->
              <div v-if="!cameraLoading && !cameraError" class="scan-reticle" ref="guideBox">
                <div class="reticle-corner tl"></div>
                <div class="reticle-corner tr"></div>
                <div class="reticle-corner bl"></div>
                <div class="reticle-corner br"></div>
                <div class="reticle-scan-line"></div>
                <div class="reticle-label">Поместите VIN-код в рамку и нажмите синюю кнопку снизу</div>
              </div>

              <!-- Лоадер камеры -->
              <div v-if="cameraLoading" class="camera-loader">
                <span class="spinner-large"></span>
                <span>Подключение к камере...</span>
              </div>

              <!-- Ошибка камеры (нет доступа или нет устройства) -->
              <div v-if="cameraError" class="camera-error-overlay">
                <span class="error-icon">🚫</span>
                <h4>Камера недоступна</h4>
                <p>{{ cameraError }}</p>
                <div class="error-actions">
                  <button type="button" class="btn-secondary" @click="initCamera">Повторить попытку</button>
                  <button type="button" class="btn-primary" @click="switchToFileUpload">Загрузить файл с устройства</button>
                </div>
              </div>
            </div>

            <!-- Элементы управления камерой -->
            <div class="camera-controls" v-if="!cameraError">
              <!-- Плашка-подсказка прямо над кнопкой затвора -->
              <div class="capture-hint-badge">
                <span class="hint-hand">👉</span>
                <span class="hint-text">Сделайте снимок</span>
                <span class="hint-arrow">👇</span>
              </div>

              <button 
                type="button" 
                class="btn-capture" 
                @click="capturePhoto"
                :disabled="cameraLoading || isProcessing"
                title="Сделать снимок"
              >
                <span class="capture-inner"></span>
              </button>

              <div class="camera-sub-controls">
                <button type="button" class="btn-sub" @click="switchToFileUpload">
                  📁 Выбрать фото из файлов
                </button>
                <label class="btn-sub btn-mobile-capture" v-if="isMobile">
                  📱 Камера смартфона
                  <input type="file" accept="image/*" capture="environment" @change="onFileSelected" style="display: none;" />
                </label>
              </div>
            </div>
          </div>

          <!-- Режим 2: Загрузка файла (Drag & drop или выбор файла) -->
          <div v-else-if="mode === 'upload'" class="upload-view">
            <div 
              class="drop-zone"
              :class="{ 'is-dragging': isDragging }"
              @dragover.prevent="isDragging = true"
              @dragleave.prevent="isDragging = false"
              @drop.prevent="onDrop"
              @click="triggerFileInput"
            >
              <input 
                id="vin-file-input"
                ref="fileInput" 
                type="file" 
                accept="image/*" 
                @change="onFileSelected" 
                style="display: none;" 
              />
              <span class="drop-icon">🖼️</span>
              <h4>Перетащите фото VIN сюда</h4>
              <p>или нажмите для выбора файла (JPG, PNG, WEBP)</p>
              <span class="drop-hint">Поддерживаются фото шильдиков, наклеек дверных проемов и рельефных номеров на металле</span>
            </div>

            <div class="upload-footer">
              <button type="button" class="btn-secondary" @click="switchToCamera">
                📹 Вернуться к веб-камере
              </button>
            </div>
          </div>

          <!-- Режим 3: Обработка / Распознавание (Processing State) -->
          <div v-if="isProcessing" class="processing-overlay">
            <div class="preview-mini-wrap" v-if="previewUrl">
              <img :src="previewUrl" alt="Предпросмотр фото" class="preview-mini-img" />
            </div>
            <div class="processing-content">
              <span class="spinner-large"></span>
              <h4>ИИ распознает VIN-код (1–2 сек)...</h4>
              <p class="processing-step">{{ processingStepText }}</p>
            </div>
          </div>

          <!-- Режим 4: Ошибка распознавания (VIN не найден) -->
          <div v-if="scanError && !isProcessing" class="scan-error-banner">
            <span class="alert-icon">⚠️</span>
            <div class="alert-text">
              <strong>Не удалось распознать VIN</strong>
              <p>{{ scanError }}</p>
            </div>
            <button type="button" class="btn-retry" @click="retryScan">Попробовать снова</button>
          </div>

          <!-- Скрытый canvas для захвата кадра с камеры -->
          <canvas ref="canvas" style="display: none;"></canvas>
        </div>

        <!-- Подвал модалки с подсказками -->
        <div class="modal-footer">
          <div class="footer-tips">
            <span>💡 <strong>Совет:</strong> Убедитесь, что все 17 символов в фокусе и нет сильных бликов. Фото удаляется с сервера сразу после распознавания.</span>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script>
export default {
  name: 'VinCameraModal',
  props: {
    isOpen: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      mode: 'camera', // 'camera' | 'upload'
      stream: null,
      cameraLoading: false,
      cameraError: null,
      isDragging: false,
      isProcessing: false,
      processingStepText: 'Предобработка OpenCV (CLAHE)...',
      previewUrl: null,
      scanError: null,
      isMobile: false
    };
  },
  watch: {
    isOpen(newVal) {
      if (newVal) {
        this.resetState();
        this.checkMobile();
        this.$nextTick(() => {
          this.initCamera();
        });
        window.addEventListener('keydown', this.handleKeyDown);
      } else {
        this.stopCamera();
        window.removeEventListener('keydown', this.handleKeyDown);
      }
    }
  },
  beforeDestroy() {
    this.stopCamera();
    window.removeEventListener('keydown', this.handleKeyDown);
    if (this.previewUrl) {
      URL.revokeObjectURL(this.previewUrl);
    }
  },
  methods: {
    checkMobile() {
      this.isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    },
    resetState() {
      this.mode = 'camera';
      this.cameraError = null;
      this.scanError = null;
      this.isProcessing = false;
      this.previewUrl = null;
    },
    handleKeyDown(e) {
      if (e.key === 'Escape' && !this.isProcessing) {
        this.handleClose();
      }
    },
    handleClose() {
      if (this.isProcessing) return;
      this.stopCamera();
      this.$emit('close');
    },
    async initCamera() {
      this.cameraLoading = true;
      this.cameraError = null;

      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        this.cameraError = 'Ваш браузер или операционная система не поддерживают прямой доступ к камере.';
        this.cameraLoading = false;
        return;
      }

      try {
        const constraints = {
          video: {
            facingMode: { ideal: 'environment' },
            width: { ideal: 1920 },
            height: { ideal: 1080 }
          },
          audio: false
        };

        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        this.stream = stream;

        this.$nextTick(() => {
          if (this.$refs.video) {
            this.$refs.video.srcObject = stream;
          }
        });
      } catch (err) {
        console.warn('Camera access error:', err);
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
          this.cameraError = 'Доступ к камере заблокирован в настройках браузера. Разрешите доступ или выберите фото из файлов.';
        } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
          this.cameraError = 'Камера или веб-камера не найдена на вашем устройстве.';
        } else {
          this.cameraError = 'Не удалось инициализировать камеру (' + (err.message || 'ошибка оборудования') + ').';
        }
      } finally {
        this.cameraLoading = false;
      }
    },
    onVideoReady() {
      this.cameraLoading = false;
    },
    stopCamera() {
      if (this.stream) {
        this.stream.getTracks().forEach(track => track.stop());
        this.stream = null;
      }
      if (this.$refs.video) {
        this.$refs.video.srcObject = null;
      }
    },
    switchToFileUpload() {
      this.stopCamera();
      this.mode = 'upload';
      this.scanError = null;
    },
    switchToCamera() {
      this.mode = 'camera';
      this.scanError = null;
      this.$nextTick(() => {
        this.initCamera();
      });
    },
    triggerFileInput() {
      if (this.$refs.fileInput) {
        this.$refs.fileInput.click();
      }
    },
    onFileSelected(e) {
      const files = e.target.files;
      if (files && files.length > 0) {
        this.processFile(files[0]);
      }
    },
    onDrop(e) {
      this.isDragging = false;
      const files = e.dataTransfer.files;
      if (files && files.length > 0) {
        this.processFile(files[0]);
      }
    },
    capturePhoto() {
      const video = this.$refs.video;
      if (!video) return;

      // Сразу отображаем оверлей с лоадером
      this.isProcessing = true;
      this.scanError = null;
      this.processingStepText = 'Кадрирование и отправка в ИИ...';

      try {
        const guideBox = this.$refs.guideBox;
        const canvas = this.$refs.canvas || document.createElement('canvas');

        let cropX = 0;
        let cropY = 0;
        let cropWidth = video.videoWidth || 1280;
        let cropHeight = video.videoHeight || 720;

        if (guideBox && video.videoWidth > 0 && video.videoHeight > 0) {
          const box = guideBox.getBoundingClientRect();
          const videoRect = video.getBoundingClientRect();

          if (videoRect.width > 0 && videoRect.height > 0) {
            // Масштаб натурального разрешения видео к его отображению на экране
            const scaleX = video.videoWidth / videoRect.width;
            const scaleY = video.videoHeight / videoRect.height;

            // Добавляем 10% запаса по бокам и 15% по высоте, чтобы случайно не срезать символы
            const padX = (box.width * 0.1) * scaleX;
            const padY = (box.height * 0.15) * scaleY;

            cropX = Math.max(0, (box.left - videoRect.left) * scaleX - padX);
            cropY = Math.max(0, (box.top - videoRect.top) * scaleY - padY);
            cropWidth = Math.min(video.videoWidth - cropX, box.width * scaleX + padX * 2);
            cropHeight = Math.min(video.videoHeight - cropY, box.height * scaleY + padY * 2);
          }
        }

        // Создаем холст ровно под размер этой аккуратной полоски
        canvas.width = Math.max(1, Math.round(cropWidth));
        canvas.height = Math.max(1, Math.round(cropHeight));

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, cropX, cropY, cropWidth, cropHeight, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(blob => {
          if (!blob) {
            this.isProcessing = false;
            this.scanError = 'Не удалось захватить кадр с камеры';
            return;
          }
          this.processBlob(blob);
        }, 'image/jpeg', 0.92);
      } catch (err) {
        console.error('Ошибка захвата кадра:', err);
        this.isProcessing = false;
        this.scanError = 'Ошибка при кадрировании: ' + (err.message || 'сбой оборудования');
      }
    },
    processFile(file) {
      if (!file.type.startsWith('image/')) {
        this.scanError = 'Пожалуйста, выберите файл изображения (JPEG, PNG или WEBP).';
        return;
      }
      this.previewUrl = URL.createObjectURL(file);
      this.sendToOcr(file);
    },
    processBlob(blob) {
      const file = new File([blob], 'camera_capture.jpg', { type: 'image/jpeg' });
      this.previewUrl = URL.createObjectURL(blob);
      this.sendToOcr(file);
    },
    async sendToOcr(file) {
      this.isProcessing = true;
      this.scanError = null;
      this.processingStepText = 'Отправка на сервер и предобработка OpenCV...';

      // Таймер для визуального отображения шагов
      const stepTimer = setTimeout(() => {
        this.processingStepText = 'Инференс EasyOCR и проверка стандарта ISO 3779...';
      }, 2500);

      try {
        const formData = new FormData();
        formData.append('photo', file);

        const apiBase = import.meta.env.VITE_API_URL || '';
        const response = await fetch(`${apiBase}/api/recognize-vin.php`, {
          method: 'POST',
          body: formData
        });

        clearTimeout(stepTimer);

        if (!response.ok) {
          throw new Error(`Ошибка сервера: ${response.status} ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success && data.vin) {
          // Успех! Передаем VIN в родительский компонент
          this.$emit('vin-detected', {
            vin: data.vin,
            confidence: data.confidence,
            checkDigitValid: data.check_digit_valid,
            executionTime: data.execution_time_sec
          });
          this.handleClose();
        } else {
          this.scanError = data.error || 'VIN-код на фотографии не обнаружен. Попробуйте сфотографировать под другим углом или с меньшим количеством бликов.';
          this.isProcessing = false;
        }
      } catch (err) {
        clearTimeout(stepTimer);
        console.error('OCR Recognition failed:', err);
        this.scanError = 'Сбой при обращении к модулю распознавания: ' + (err.message || 'проверьте подключение к бэкенду');
        this.isProcessing = false;
      }
    },
    retryScan() {
      this.scanError = null;
      this.isProcessing = false;
      if (this.mode === 'camera' && !this.stream) {
        this.initCamera();
      }
    }
  }
};
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(8, 12, 22, 0.85);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999999;
  padding: 16px;
}

.modal-container {
  width: 100%;
  max-width: 680px;
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 40px rgba(59, 130, 246, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: modal-pop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modal-pop {
  from {
    transform: scale(0.94) translateY(10px);
    opacity: 0;
  }
  to {
    transform: scale(1) translateY(0);
    opacity: 1;
  }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(17, 24, 39, 0.6);
}

.header-title {
  display: flex;
  align-items: center;
  gap: 14px;
}

.header-icon {
  font-size: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  background: rgba(59, 130, 246, 0.12);
  border: 1px solid rgba(59, 130, 246, 0.25);
  border-radius: 12px;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: #f3f4f6;
  letter-spacing: -0.01em;
}

.header-desc {
  margin: 4px 0 0;
  font-size: 0.85rem;
  color: #9ca3af;
  line-height: 1.35;
}

.btn-close {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #9ca3af;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  font-size: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.btn-close:hover {
  background: rgba(239, 68, 68, 0.2);
  border-color: rgba(239, 68, 68, 0.4);
  color: #ef4444;
}

.modal-body {
  position: relative;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Вид камеры */
.video-container {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  background: #030712;
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
}

.camera-stream {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Рамка для выравнивания номера (видоискатель) */
.scan-reticle {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 82%;
  height: 36%;
  border: 1px dashed rgba(59, 130, 246, 0.4);
  border-radius: 8px;
  pointer-events: none;
  box-shadow: 0 0 0 9999px rgba(3, 7, 18, 0.45);
}

.reticle-corner {
  position: absolute;
  width: 18px;
  height: 18px;
  border-color: #3b82f6;
  border-style: solid;
}

.reticle-corner.tl { top: -2px; left: -2px; border-width: 3px 0 0 3px; border-top-left-radius: 6px; }
.reticle-corner.tr { top: -2px; right: -2px; border-width: 3px 3px 0 0; border-top-right-radius: 6px; }
.reticle-corner.bl { bottom: -2px; left: -2px; border-width: 0 0 3px 3px; border-bottom-left-radius: 6px; }
.reticle-corner.br { bottom: -2px; right: -2px; border-width: 0 3px 3px 0; border-bottom-right-radius: 6px; }

.reticle-scan-line {
  position: absolute;
  left: 0;
  width: 100%;
  height: 2px;
  background: linear-gradient(90deg, transparent, #3b82f6, #60a5fa, transparent);
  box-shadow: 0 0 10px #3b82f6;
  animation: scan-vertical 2.2s ease-in-out infinite alternate;
}

@keyframes scan-vertical {
  from { top: 10%; }
  to { top: 90%; }
}

.reticle-label {
  position: absolute;
  bottom: -32px;
  left: 50%;
  transform: translateX(-50%);
  width: 95%;
  text-align: center;
  font-size: 0.82rem;
  color: #93c5fd;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.9);
  font-weight: 600;
  line-height: 1.25;
}

.camera-loader,
.camera-error-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  background: #0b0f19;
  color: #9ca3af;
  padding: 24px;
  text-align: center;
}

.camera-error-overlay h4 {
  margin: 0;
  color: #f87171;
  font-size: 1.1rem;
}

.camera-error-overlay p {
  margin: 0;
  font-size: 0.85rem;
  color: #9ca3af;
  max-width: 380px;
}

.error-actions {
  display: flex;
  gap: 10px;
  margin-top: 12px;
}

/* Кнопка спуска камеры и подсказка */
.camera-controls {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding-top: 4px;
}

.capture-hint-badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 16px;
  background: rgba(59, 130, 246, 0.16);
  border: 1px solid rgba(59, 130, 246, 0.4);
  border-radius: 20px;
  color: #93c5fd;
  font-size: 0.86rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  box-shadow: 0 0 16px rgba(59, 130, 246, 0.25);
  animation: pulse-bounce 2s ease-in-out infinite;
  pointer-events: none;
  user-select: none;
}

.hint-hand {
  font-size: 1rem;
}

.hint-text {
  font-size: 0.86rem;
}

.hint-arrow {
  font-size: 0.95rem;
  display: inline-block;
  animation: arrow-bounce 1s ease-in-out infinite alternate;
}

@keyframes pulse-bounce {
  0%, 100% {
    transform: translateY(0);
    box-shadow: 0 0 12px rgba(59, 130, 246, 0.2);
  }
  50% {
    transform: translateY(-2px);
    box-shadow: 0 0 22px rgba(59, 130, 246, 0.45);
    border-color: rgba(96, 165, 250, 0.7);
  }
}

@keyframes arrow-bounce {
  from {
    transform: translateY(-1px);
  }
  to {
    transform: translateY(3px);
  }
}

.btn-capture {
  width: 68px;
  height: 68px;
  border-radius: 50%;
  border: 3px solid rgba(255, 255, 255, 0.8);
  background: transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 0 20px rgba(59, 130, 246, 0.4);
}

.btn-capture:hover:not(:disabled) {
  transform: scale(1.06);
  border-color: #60a5fa;
  box-shadow: 0 0 30px rgba(59, 130, 246, 0.6);
}

.btn-capture:active:not(:disabled) {
  transform: scale(0.96);
}

.capture-inner {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  transition: all 0.2s ease;
}

.btn-capture:hover:not(:disabled) .capture-inner {
  background: linear-gradient(135deg, #60a5fa, #3b82f6);
}

.camera-sub-controls {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  justify-content: center;
}

.btn-sub {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #d1d5db;
  padding: 8px 14px;
  border-radius: 10px;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-sub:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  border-color: rgba(255, 255, 255, 0.2);
}

/* Зона Drag-and-drop */
.drop-zone {
  border: 2px dashed rgba(59, 130, 246, 0.35);
  border-radius: 14px;
  background: rgba(17, 24, 39, 0.4);
  padding: 44px 24px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.drop-zone:hover,
.drop-zone.is-dragging {
  border-color: #3b82f6;
  background: rgba(59, 130, 246, 0.08);
  transform: translateY(-2px);
}

.drop-icon {
  font-size: 44px;
  margin-bottom: 6px;
}

.drop-zone h4 {
  margin: 0;
  font-size: 1.15rem;
  color: #f3f4f6;
}

.drop-zone p {
  margin: 0;
  font-size: 0.88rem;
  color: #9ca3af;
}

.drop-hint {
  font-size: 0.76rem;
  color: #6b7280;
  margin-top: 6px;
  max-width: 440px;
}

.upload-footer {
  display: flex;
  justify-content: center;
  margin-top: 4px;
}

/* Оверлей обработки */
.processing-overlay {
  position: absolute;
  inset: 0;
  background: rgba(11, 15, 25, 0.92);
  backdrop-filter: blur(6px);
  z-index: 10;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 24px;
  border-radius: 14px;
  text-align: center;
}

.preview-mini-wrap {
  width: 220px;
  max-width: 90%;
  height: 65px;
  border-radius: 10px;
  overflow: hidden;
  border: 2px solid rgba(59, 130, 246, 0.4);
  background: #030712;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
}

.preview-mini-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.processing-content h4 {
  margin: 0;
  color: #f3f4f6;
  font-size: 1.15rem;
}

.processing-step {
  margin: 6px 0 0;
  font-size: 0.85rem;
  color: #60a5fa;
  font-family: 'JetBrains Mono', monospace;
}

/* Ошибка распознавания */
.scan-error-banner {
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 12px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 14px;
}

.alert-icon {
  font-size: 24px;
  flex-shrink: 0;
}

.alert-text {
  flex: 1;
}

.alert-text strong {
  display: block;
  font-size: 0.9rem;
  color: #f87171;
}

.alert-text p {
  margin: 2px 0 0;
  font-size: 0.8rem;
  color: #fca5a5;
  line-height: 1.35;
}

.btn-retry {
  background: rgba(239, 68, 68, 0.2);
  border: 1px solid rgba(239, 68, 68, 0.4);
  color: #fca5a5;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.8rem;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
}

.btn-retry:hover {
  background: rgba(239, 68, 68, 0.35);
  color: #fff;
}

/* Подвал */
.modal-footer {
  padding: 14px 24px;
  background: rgba(17, 24, 39, 0.4);
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.footer-tips {
  font-size: 0.8rem;
  color: #9ca3af;
  line-height: 1.4;
}

.footer-tips strong {
  color: #e5e7eb;
}

/* Общие кнопки */
.btn-primary {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: #fff;
  border: none;
  padding: 9px 16px;
  border-radius: 10px;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #60a5fa, #3b82f6);
  transform: translateY(-1px);
}

.btn-secondary {
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #e5e7eb;
  padding: 9px 16px;
  border-radius: 10px;
  font-size: 0.88rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.14);
}

.spinner-large {
  width: 38px;
  height: 38px;
  border: 3px solid rgba(59, 130, 246, 0.2);
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 640px) {
  .modal-container {
    border-radius: 16px;
  }
  .modal-header {
    padding: 16px;
  }
  .modal-body {
    padding: 16px;
  }
  .modal-footer {
    padding: 12px 16px;
  }
  .video-container {
    aspect-ratio: 4 / 3;
  }
  .reticle-label {
    font-size: 0.74rem;
    bottom: -30px;
    width: 96%;
  }
  .capture-hint-badge {
    font-size: 0.8rem;
    padding: 5px 12px;
  }
  .btn-capture {
    width: 60px;
    height: 60px;
  }
  .capture-inner {
    width: 46px;
    height: 46px;
  }
}
</style>
