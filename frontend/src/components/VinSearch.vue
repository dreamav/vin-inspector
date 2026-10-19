<template>
  <div class="search-box glass-panel">
    <div class="search-header">
      <div class="title-wrap">
        <span class="search-icon">🔍</span>
        <div>
          <h2>Проверка автомобиля и деталей</h2>
          <p class="subtitle">
            <span v-if="!decodedVehicle && !vehicle">Шаг 1: Введите VIN для определения марки и характеристик</span>
            <span v-else-if="decodedVehicle && !vehicle" class="step-ready-subtitle">Автомобиль определен. Перейдите к каталогу запчастей</span>
            <span v-else class="step2-subtitle">Шаг 2: Введите OEM-код запчасти для проверки совместимости</span>
          </p>
        </div>
      </div>

      <!-- Кнопки быстрого выбора примеров -->
      <div v-if="!vehicle" class="presets">
        <span class="preset-label">Примеры:</span>
        <button 
          v-for="item in activePresets" 
          :key="item.vin"
          type="button" 
          class="btn-preset" 
          @click="selectPreset(item)"
          :title="item.label"
        >
          <span class="preset-icon" v-if="item.icon">{{ item.icon }}</span>
          {{ item.short }}
        </button>
      </div>
    </div>

    <form @submit.prevent="handleSubmit" class="search-form">
      <div class="fields-stack">
        <!-- Поле 1: VIN код (Шаг 1) -->
        <div class="field-group">
          <div class="field-label-row">
            <label for="vin-input" class="field-label">
              <span class="label-badge" :class="{ 'badge-done': decodedVehicle || vehicle }">
                {{ (decodedVehicle || vehicle) ? '✓' : '1' }}
              </span>
              <span>VIN-код автомобиля</span>
              <span class="required-tag" v-if="!decodedVehicle && !vehicle">Обязательно</span>
            </label>
            <button 
              v-if="decodedVehicle || vehicle" 
              type="button" 
              class="btn-change-vin" 
              @click="resetAll"
              title="Ввести другой VIN"
            >
              Сменить VIN
            </button>
          </div>

          <div class="input-wrapper" :class="{ 'has-error': vinError, 'is-locked': !!decodedVehicle || !!vehicle }">
            <input
              id="vin-input"
              ref="vinInput"
              type="text"
              v-model="vinInput"
              @input="onVinInput"
              placeholder="Например: 5LMJJ2J54CEL07288 или 3PCAJ5M34KF147171"
              maxlength="17"
              autocomplete="off"
              spellcheck="false"
              :disabled="loading || decoding || !!decodedVehicle || !!vehicle"
            />
            <div class="input-meta">
              <span class="char-count" :class="{ valid: vinInput.length === 17 }">
                {{ vinInput.length }}/17
              </span>
              <button 
                v-if="vinInput.length > 0 && !decodedVehicle && !vehicle" 
                type="button" 
                class="clear-btn" 
                @click="clearVinInput"
                title="Очистить VIN"
              >
                ✕
              </button>
            </div>
          </div>

          <div v-if="vinError" class="validation-warning">
            ⚠️ {{ vinError }}
          </div>

          <!-- Отображение найденной машины просто текстом (через NHTSA API) -->
          <transition name="slide-fade">
            <div v-if="decodedVehicle && !vehicle" class="decoded-car-card">
              <div class="decoded-header">
                <span class="nhtsa-badge">✓ Определено по базе NHTSA</span>
                <span class="decoded-vin-code">{{ decodedVehicle.vin }}</span>
              </div>
              
              <div class="decoded-main-row">
                <span class="decoded-car-icon">🚘</span>
                <div class="decoded-car-info">
                  <h3 class="decoded-car-title">{{ decodedVehicle.carTitle }}</h3>
                  <div class="decoded-tags-list">
                    <span class="tag-item" v-if="decodedVehicle.engine">⚙️ {{ decodedVehicle.engine }}</span>
                    <span class="tag-item" v-if="decodedVehicle.driveType">🛞 {{ decodedVehicle.driveType }}</span>
                    <span class="tag-item" v-if="decodedVehicle.bodyClass">🚙 {{ decodedVehicle.bodyClass }}</span>
                    <span class="tag-item" v-if="decodedVehicle.plantCountry">📍 Завод: {{ decodedVehicle.plantCountry }}</span>
                  </div>
                </div>
              </div>

              <div class="decoded-footer" v-if="decodedVehicle.catalogHost">
                <span>Целевой каталог запчастей: <strong>{{ decodedVehicle.catalogHost }}</strong></span>
              </div>
            </div>
          </transition>

          <!-- Плашка успешного определения авто в каталоге запчастей -->
          <div v-if="vehicle" class="vehicle-identified-banner">
            <span class="check-icon">✓</span>
            <div class="identified-info">
              <span class="identified-label">Каталог подключен:</span>
              <strong class="identified-title">{{ vehicle.title || (vehicle.make + ' ' + (vehicle.model || '')) }}</strong>
              <span class="site-tag-inline" v-if="vehicle.siteUrl">
                ({{ getHost(vehicle.siteUrl) }})
              </span>
            </div>
          </div>
        </div>

        <!-- Поле 2: OEM-код запчасти (Шаг 2 - ПОЯВЛЯЕТСЯ ТОЛЬКО ПОСЛЕ ПОИСКА В КАТАЛОГЕ) -->
        <transition name="slide-fade">
          <div v-if="vehicle" class="field-group oem-group">
            <label for="oem-input" class="field-label">
              <span class="label-badge badge-active">2</span>
              <span>OEM-код запчасти</span>
              <span class="badge-step2-tag" v-if="vehicle.siteUrl">
                Каталог {{ getHost(vehicle.siteUrl) }}
              </span>
            </label>
            <div class="input-wrapper oem-wrapper" :class="{ 'has-error': oemError }">
              <input
                id="oem-input"
                ref="oemInput"
                type="text"
                v-model="oemInput"
                @input="onOemInput"
                placeholder="Например: 7L1Z-19703-B, 48430-5NB0A или 2761800009"
                autocomplete="off"
                spellcheck="false"
                :disabled="oemLoading"
              />
              <div class="input-meta">
                <button 
                  v-if="oemInput.length > 0" 
                  type="button" 
                  class="clear-btn" 
                  @click="clearOemInput"
                  title="Очистить OEM"
                >
                  ✕
                </button>
              </div>
            </div>
            <div v-if="oemError" class="validation-warning">
              ⚠️ {{ oemError }}
            </div>
          </div>
        </transition>
      </div>

      <div class="form-actions">
        <!-- Кнопка 1: Определить авто (через NHTSA) -->
        <button 
          v-if="!decodedVehicle && !vehicle"
          type="submit" 
          class="btn-primary submit-btn btn-decode" 
          :disabled="!isValidVin || decoding"
        >
          <span v-if="decoding" class="spinner"></span>
          <span v-else>Определить авто</span>
          <span v-if="!decoding" class="arrow-icon">🔍</span>
        </button>

        <!-- Кнопка 2: Найти на сайте каталога (активируется после распознавания NHTSA) -->
        <button 
          v-else-if="decodedVehicle && !vehicle"
          type="button" 
          class="btn-primary submit-btn btn-search-catalog" 
          :disabled="loading"
          @click="handleSearchCatalog"
        >
          <span v-if="loading" class="spinner"></span>
          <span v-else-if="decodedVehicle.catalogHost">Найти на {{ decodedVehicle.catalogHost }}</span>
          <span v-else>Найти запчасти</span>
          <span v-if="!loading" class="arrow-icon">→</span>
        </button>

        <!-- Кнопка 3: Проверить OEM деталь (Шаг 2) -->
        <button 
          v-else
          type="submit" 
          class="btn-primary submit-btn btn-oem-submit" 
          :disabled="!isValidOem || oemLoading"
        >
          <span v-if="oemLoading" class="spinner"></span>
          <span v-else>Проверить OEM деталь</span>
          <span v-if="!oemLoading" class="arrow-icon">🔍</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script>
export default {
  name: 'VinSearch',
  props: {
    loading: {
      type: Boolean,
      default: false
    },
    decoding: {
      type: Boolean,
      default: false
    },
    decodedVehicle: {
      type: Object,
      default: null
    },
    vehicle: {
      type: Object,
      default: null
    },
    oemLoading: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      vinInput: '',
      oemInput: '',
      vinError: null,
      oemError: null,
      allPresets: [
        { 
          short: 'Lincoln Navigator', 
          label: '2012 Lincoln Navigator (5LMJJ2J54CEL07288)', 
          vin: '5LMJJ2J54CEL07288', 
          oem: '7L1Z-19703-B',
          icon: '👑'
        },
        { 
          short: 'Infiniti QX50', 
          label: '2019 Infiniti QX50 (3PCAJ5M34KF147171)', 
          vin: '3PCAJ5M34KF147171', 
          oem: '48430-5NB0A',
          icon: '🏎️'
        },
        { 
          short: 'Mercedes GLE', 
          label: '2016 Mercedes GLE350 (4JGDA5HB8GA646272)', 
          vin: '4JGDA5HB8GA646272', 
          oem: '2761800009',
          icon: '⭐'
        },
        { 
          short: 'BMW X5', 
          label: 'BMW X5 (5UXCR6C05M9B12345)', 
          vin: '5UXCR6C05M9B12345', 
          oem: '',
          icon: '🔵'
        },
        { 
          short: 'Toyota Camry', 
          label: 'Toyota Camry (4T1B11HK5LU123456)', 
          vin: '4T1B11HK5LU123456', 
          oem: '',
          icon: '🔴'
        }
      ]
    };
  },
  watch: {
    vehicle(newVal) {
      if (newVal) {
        if (newVal.vin) {
          this.vinInput = newVal.vin;
        }
        if (newVal.oem) {
          this.oemInput = newVal.oem;
        }
        this.$nextTick(() => {
          if (this.$refs.oemInput) {
            this.$refs.oemInput.focus();
          }
        });
      }
    }
  },
  computed: {
    activePresets() {
      return this.allPresets;
    },
    isValidVin() {
      const clean = this.vinInput.trim().toUpperCase();
      return clean.length === 17 && !/[IOQ]/i.test(clean) && /^[A-HJ-NPR-Z0-9]{17}$/i.test(clean);
    },
    isValidOem() {
      return this.oemInput.trim().length >= 3;
    }
  },
  methods: {
    onVinInput(event) {
      let val = event.target.value.toUpperCase().replace(/\s+/g, '');
      
      if (/[IOQ]/.test(val)) {
        this.vinError = 'VIN-код не может содержать буквы I, O, Q';
      } else {
        this.vinError = null;
      }

      this.vinInput = val;
    },
    onOemInput(event) {
      this.oemInput = event.target.value.toUpperCase().trimStart();
      this.oemError = null;
    },
    selectPreset(item) {
      this.vinInput = item.vin;
      this.oemInput = item.oem || '';
      this.vinError = null;
      this.$emit('decode-vin', this.vinInput);
    },
    clearVinInput() {
      this.vinInput = '';
      this.vinError = null;
      this.$nextTick(() => {
        this.$refs.vinInput.focus();
      });
    },
    clearOemInput() {
      this.oemInput = '';
      this.$nextTick(() => {
        this.$refs.oemInput.focus();
      });
    },
    resetAll() {
      this.$emit('reset-vin');
      this.vinInput = '';
      this.oemInput = '';
      this.vinError = null;
      this.oemError = null;
      this.$nextTick(() => {
        if (this.$refs.vinInput) {
          this.$refs.vinInput.focus();
        }
      });
    },
    handleSubmit() {
      if (!this.decodedVehicle && !this.vehicle) {
        // Шаг 1: Запрос к NHTSA для определения авто
        if (!this.isValidVin) {
          if (this.vinInput.length < 17) {
            this.vinError = `Длина VIN должна быть ровно 17 символов (введено ${this.vinInput.length})`;
          }
          return;
        }
        this.vinError = null;
        this.$emit('decode-vin', this.vinInput.trim().toUpperCase());
      } else if (this.decodedVehicle && !this.vehicle) {
        // Переход к поиску на сайте каталога
        this.handleSearchCatalog();
      } else {
        // Шаг 2: Проверка OEM детали
        if (!this.isValidOem) {
          this.oemError = 'Введите корректный OEM-код детали (минимум 3 символа)';
          return;
        }
        this.oemError = null;
        this.$emit('search-oem', {
          vin: this.vinInput.trim().toUpperCase(),
          oem: this.oemInput.trim().toUpperCase(),
          brand: (this.vehicle && this.vehicle.brandId) || (this.decodedVehicle && this.decodedVehicle.brandId) || ''
        });
      }
    },
    handleSearchCatalog() {
      if (!this.decodedVehicle) return;
      this.$emit('search-catalog', {
        vin: this.vinInput.trim().toUpperCase(),
        brandId: this.decodedVehicle.brandId,
        catalogSite: this.decodedVehicle.catalogSite,
        catalogSiteCode: this.decodedVehicle.catalogSiteCode
      });
    },
    getHost(url) {
      if (!url) return '';
      try {
        const u = new URL(url);
        return u.hostname;
      } catch (e) {
        return url.replace(/^https?:\/\//, '').replace(/\/.*$/, '');
      }
    }
  }
};
</script>

<style scoped>
.search-box {
  padding: 1.75rem 2rem;
  margin-bottom: 2rem;
  position: relative;
  overflow: visible;
}

.search-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.title-wrap {
  display: flex;
  align-items: center;
  gap: 0.9rem;
}

.search-icon {
  font-size: 1.8rem;
  background: rgba(56, 189, 248, 0.1);
  padding: 0.5rem;
  border-radius: 12px;
  border: 1px solid rgba(56, 189, 248, 0.2);
}

.title-wrap h2 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--text-main);
  letter-spacing: -0.02em;
}

.subtitle {
  font-size: 0.85rem;
  color: var(--text-muted);
  margin-top: 0.2rem;
}

.step-ready-subtitle {
  color: #38bdf8;
  font-weight: 600;
}

.step2-subtitle {
  color: #a5b4fc;
  font-weight: 500;
}

.presets {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.preset-label {
  font-size: 0.78rem;
  color: var(--text-dim);
  margin-right: 0.2rem;
}

.btn-preset {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--border-subtle);
  color: var(--accent-cyan);
  padding: 0.35rem 0.65rem;
  border-radius: 6px;
  font-size: 0.78rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}

.preset-icon {
  font-size: 0.85rem;
}

.btn-preset:hover {
  background: rgba(56, 189, 248, 0.15);
  border-color: rgba(56, 189, 248, 0.4);
  transform: translateY(-1px);
}

.search-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.fields-stack {
  display: flex;
  flex-direction: column;
  gap: 1.2rem;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.field-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.field-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-main);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.label-badge {
  background: rgba(56, 189, 248, 0.2);
  color: var(--accent-cyan);
  width: 22px;
  height: 22px;
  border-radius: 50%;
  font-size: 0.75rem;
  font-family: var(--font-mono);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

.label-badge.badge-done {
  background: rgba(16, 185, 129, 0.2);
  color: var(--success);
}

.label-badge.badge-active {
  background: rgba(99, 102, 241, 0.25);
  color: #a5b4fc;
}

.required-tag {
  font-size: 0.72rem;
  color: var(--accent-cyan);
  background: rgba(56, 189, 248, 0.1);
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
  font-weight: 500;
}

.badge-step2-tag {
  font-size: 0.72rem;
  color: #a5b4fc;
  background: rgba(99, 102, 241, 0.15);
  border: 1px solid rgba(99, 102, 241, 0.3);
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
  font-weight: 500;
}

.btn-change-vin {
  background: none;
  border: 1px solid var(--border-subtle);
  color: var(--text-dim);
  font-size: 0.75rem;
  padding: 0.2rem 0.6rem;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-change-vin:hover {
  color: var(--accent-cyan);
  border-color: var(--accent-cyan);
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-wrapper input {
  width: 100%;
  background: var(--bg-input);
  border: 1.5px solid var(--border-subtle);
  color: #ffffff;
  font-family: var(--font-mono);
  font-size: 1.15rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  padding: 0.85rem 4.5rem 0.85rem 1.1rem;
  border-radius: var(--radius-sm);
  outline: none;
  transition: all 0.25s ease;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.4);
}

.input-wrapper.is-locked input {
  opacity: 0.75;
  background: rgba(13, 19, 33, 0.5);
  border-color: rgba(16, 185, 129, 0.3);
}

.oem-wrapper input {
  font-size: 1.15rem;
  letter-spacing: 0.05em;
  border-color: rgba(99, 102, 241, 0.35);
  background: rgba(18, 22, 40, 0.95);
}

.oem-wrapper input:focus {
  border-color: #818cf8;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25), inset 0 2px 4px rgba(0, 0, 0, 0.4);
}

.input-wrapper input:focus {
  border-color: var(--border-focus);
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.25), inset 0 2px 4px rgba(0, 0, 0, 0.4);
}

.input-wrapper.has-error input {
  border-color: var(--danger);
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
}

.input-meta {
  position: absolute;
  right: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.char-count {
  font-family: var(--font-mono);
  font-size: 0.8rem;
  color: var(--text-dim);
  background: rgba(0, 0, 0, 0.3);
  padding: 0.2rem 0.45rem;
  border-radius: 4px;
}

.char-count.valid {
  color: var(--success);
  background: var(--success-bg);
  font-weight: 700;
}

.clear-btn {
  background: none;
  border: none;
  color: var(--text-dim);
  cursor: pointer;
  font-size: 0.9rem;
  padding: 0.2rem;
  transition: color 0.2s;
}

.clear-btn:hover {
  color: var(--text-main);
}

/* Карточка найденной машины просто текстом (NHTSA) */
.decoded-car-card {
  background: rgba(15, 23, 42, 0.85);
  border: 1.5px solid rgba(56, 189, 248, 0.35);
  border-radius: var(--radius-sm);
  padding: 1.1rem 1.25rem;
  margin-top: 0.6rem;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  animation: fadeIn 0.25s ease;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.decoded-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  padding-bottom: 0.5rem;
}

.nhtsa-badge {
  font-size: 0.75rem;
  font-weight: 700;
  color: #38bdf8;
  background: rgba(56, 189, 248, 0.12);
  border: 1px solid rgba(56, 189, 248, 0.25);
  padding: 0.15rem 0.55rem;
  border-radius: 4px;
}

.decoded-vin-code {
  font-family: var(--font-mono);
  font-size: 0.82rem;
  color: var(--text-dim);
  letter-spacing: 0.05em;
}

.decoded-main-row {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.decoded-car-icon {
  font-size: 2rem;
  background: rgba(56, 189, 248, 0.1);
  padding: 0.4rem;
  border-radius: 10px;
}

.decoded-car-info {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.decoded-car-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: #ffffff;
  letter-spacing: -0.01em;
}

.decoded-tags-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.tag-item {
  font-size: 0.78rem;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid var(--border-subtle);
  color: var(--text-muted);
  padding: 0.2rem 0.55rem;
  border-radius: 5px;
}

.decoded-footer {
  font-size: 0.8rem;
  color: var(--text-dim);
  border-top: 1px dashed rgba(255, 255, 255, 0.08);
  padding-top: 0.5rem;
}

.decoded-footer strong {
  color: var(--accent-cyan);
  font-family: var(--font-mono);
}

/* Баннер каталога деталей */
.vehicle-identified-banner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.25);
  padding: 0.65rem 0.9rem;
  border-radius: var(--radius-sm);
  margin-top: 0.35rem;
  animation: fadeIn 0.3s ease;
}

.check-icon {
  background: var(--success);
  color: #ffffff;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 800;
}

.identified-info {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.identified-label {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.identified-title {
  color: #a7f3d0;
  font-size: 0.95rem;
  font-weight: 700;
}

.site-tag-inline {
  font-size: 0.78rem;
  color: var(--text-dim);
  font-family: var(--font-mono);
}

.form-actions {
  display: flex;
  justify-content: flex-start;
  margin-top: 0.25rem;
}

.submit-btn {
  min-width: 220px;
}

.btn-decode {
  background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%);
}

.btn-search-catalog {
  background: linear-gradient(135deg, #059669 0%, #10b981 100%);
  box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
}

.btn-oem-submit {
  background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
  box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.arrow-icon {
  font-size: 1.1rem;
  transition: transform 0.2s;
}

.submit-btn:hover .arrow-icon {
  transform: translateX(3px);
}

.validation-warning {
  margin-top: 0.35rem;
  color: #fca5a5;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

/* Transitions */
.slide-fade-enter-active {
  transition: all 0.35s ease;
}
.slide-fade-leave-active {
  transition: all 0.25s cubic-bezier(1, 0.5, 0.8, 1);
}
.slide-fade-enter, .slide-fade-leave-to {
  transform: translateY(-10px);
  opacity: 0;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
  .submit-btn {
    width: 100%;
  }
}
</style>
