<template>
  <div class="vehicle-details glass-panel">
    <!-- Header banner with title & actions -->
    <div class="details-header">
      <div class="vehicle-heading">
        <span class="car-badge">{{ vehicle.year || 'Н/Д' }}</span>
        <div>
          <h1 class="vehicle-title">
            {{ vehicle.title || ((vehicle.make || 'Автомобиль') + ' ' + (vehicle.model || '')) }}
            <span v-if="vehicle.trim" class="vehicle-trim">{{ vehicle.trim }}</span>
          </h1>
          <div class="vin-pill-wrap">
            <div class="vin-pill">
              <span class="pill-label">VIN:</span>
              <code class="vin-value">{{ vehicle.vin }}</code>
            </div>
            <div v-if="vehicle.brand" class="vin-pill brand-pill">
              <span class="pill-label">Марка:</span>
              <span class="brand-pill-value">{{ vehicle.brand }}</span>
            </div>
            <div v-if="vehicle.oem" class="vin-pill oem-pill" :class="'oem-' + vehicle.oemStatus">
              <span class="pill-label">OEM:</span>
              <code class="vin-value oem-value">{{ vehicle.oem }}</code>
              <span v-if="vehicle.confirmText" class="oem-fit-tag" :class="vehicle.oemStatus">
                {{ vehicle.confirmText }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="header-actions">
        <button 
          type="button" 
          class="btn-secondary" 
          @click="copyReport"
          :title="copied ? 'Скопировано!' : 'Скопировать все данные в буфер'"
        >
          <span>{{ copied ? '✓ Скопировано' : '📋 Копировать отчет' }}</span>
        </button>
        <button 
          type="button" 
          class="btn-secondary" 
          @click="printReport"
          title="Распечатать карточку"
        >
          <span>🖨️ Печать</span>
        </button>
      </div>
    </div>

    <!-- Specs View -->
    <div class="specs-content">
      <!-- Section: Основные данные -->
      <div class="spec-section">
        <h3 class="section-title">
          <span class="section-icon">🚗</span> Основная информация
        </h3>
        <div class="spec-grid">
          <div class="spec-item">
            <span class="spec-label">Марка</span>
            <span class="spec-value highlight">{{ vehicle.make || '—' }}</span>
          </div>
          <div class="spec-item">
            <span class="spec-label">Модель</span>
            <span class="spec-value highlight">{{ vehicle.model || '—' }}</span>
          </div>
          <div class="spec-item">
            <span class="spec-label">Год выпуска</span>
            <span class="spec-value">{{ vehicle.year || '—' }}</span>
          </div>
          <div class="spec-item">
            <span class="spec-label">Тип кузова</span>
            <span class="spec-value">{{ vehicle.bodyClass || vehicle.bodyType || '—' }}</span>
          </div>
          <div class="spec-item">
            <span class="spec-label">Цвет кузова</span>
            <span class="spec-value">{{ vehicle.color || '—' }}</span>
          </div>
          <div class="spec-item">
            <span class="spec-label">Страна сборки</span>
            <span class="spec-value">{{ vehicle.country || vehicle.plantCountry || '—' }}</span>
          </div>
        </div>
      </div>

      <!-- Section: Двигатель и трансмиссия -->
      <div class="spec-section">
        <h3 class="section-title">
          <span class="section-icon">⚙️</span> Силовая установка и трансмиссия
        </h3>
        <div class="spec-grid">
          <div class="spec-item">
            <span class="spec-label">Двигатель</span>
            <span class="spec-value">{{ vehicle.engine || '—' }}</span>
          </div>
          <div class="spec-item">
            <span class="spec-label">Объем двигателя</span>
            <span class="spec-value">{{ vehicle.displacement || vehicle.engineDisplacement || '—' }}</span>
          </div>
          <div class="spec-item">
            <span class="spec-label">Мощность</span>
            <span class="spec-value">{{ vehicle.horsepower || vehicle.enginePower || '—' }}</span>
          </div>
          <div class="spec-item">
            <span class="spec-label">Тип топлива</span>
            <span class="spec-value">{{ vehicle.fuelType || '—' }}</span>
          </div>
          <div class="spec-item">
            <span class="spec-label">Привод</span>
            <span class="spec-value">{{ vehicle.driveType || '—' }}</span>
          </div>
          <div class="spec-item">
            <span class="spec-label">Коробка передач</span>
            <span class="spec-value">{{ vehicle.transmission || '—' }}</span>
          </div>
        </div>
      </div>

      <!-- Section: Дополнительно / Опции -->
      <div v-if="vehicle.extra && Object.keys(vehicle.extra).length" class="spec-section">
        <h3 class="section-title">
          <span class="section-icon">📋</span> Дополнительные параметры
        </h3>
        <div class="spec-grid">
          <div 
            v-for="(val, key) in vehicle.extra" 
            :key="key" 
            class="spec-item"
          >
            <span class="spec-label">{{ formatKey(key) }}</span>
            <span class="spec-value">{{ val || '—' }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'VehicleDetails',
  props: {
    vehicle: {
      type: Object,
      required: true
    }
  },
  data() {
    return {
      copied: false
    };
  },
  methods: {
    formatKey(key) {
      // Преобразуем camelCase или snake_case в читаемый вид
      return key
        .replace(/_/g, ' ')
        .replace(/([A-Z])/g, ' $1')
        .replace(/^./, (str) => str.toUpperCase());
    },
    copyReport() {
      const v = this.vehicle;
      const textParts = [
        `=== ОТЧЕТ ПО АВТОМОБИЛЮ ===`,
        `VIN: ${v.vin}`,
        v.oem ? `OEM код детали: ${v.oem}` : null,
        `Марка и модель: ${v.make || ''} ${v.model || ''} ${v.trim || ''}`.trim(),
        `Год выпуска: ${v.year || 'Н/Д'}`,
        `Кузов: ${v.bodyClass || v.bodyType || 'Н/Д'}`,
        `Двигатель: ${v.engine || 'Н/Д'} (${v.displacement || ''}, ${v.fuelType || ''})`,
        `Привод: ${v.driveType || 'Н/Д'}`,
        `КПП: ${v.transmission || 'Н/Д'}`,
        `Страна: ${v.country || v.plantCountry || 'Н/Д'}`
      ].filter(Boolean);
      const text = textParts.join('\n');

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
          this.copied = true;
          setTimeout(() => { this.copied = false; }, 2500);
        });
      } else {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        this.copied = true;
        setTimeout(() => { this.copied = false; }, 2500);
      }
    },
    printReport() {
      window.print();
    }
  }
};
</script>

<style scoped>
.vehicle-details {
  padding: 2rem;
  margin-bottom: 2rem;
}

.details-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1.5rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--border-subtle);
}

.vehicle-heading {
  display: flex;
  align-items: center;
  gap: 1.25rem;
}

.car-badge {
  background: var(--accent-gradient);
  color: #fff;
  font-size: 1.15rem;
  font-weight: 800;
  padding: 0.6rem 0.9rem;
  border-radius: var(--radius-sm);
  box-shadow: 0 4px 15px rgba(56, 189, 248, 0.3);
}

.vehicle-title {
  font-size: 1.6rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--text-main);
  line-height: 1.2;
}

.vehicle-trim {
  font-size: 1.1rem;
  font-weight: 500;
  color: var(--accent-cyan);
  margin-left: 0.4rem;
}

.vin-pill-wrap {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
  margin-top: 0.4rem;
}

.vin-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: rgba(0, 0, 0, 0.35);
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
  border: 1px solid var(--border-subtle);
}

.oem-pill {
  background: rgba(99, 102, 241, 0.12);
  border-color: rgba(99, 102, 241, 0.35);
}

.pill-label {
  font-size: 0.75rem;
  color: var(--text-dim);
  text-transform: uppercase;
  font-weight: 600;
}

.vin-value {
  font-family: var(--font-mono);
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--accent-cyan);
  letter-spacing: 0.08em;
}

.oem-value {
  color: #a5b4fc;
}

.brand-pill {
  background: rgba(99, 102, 241, 0.15);
  border-color: rgba(99, 102, 241, 0.35);
}

.brand-pill-value {
  font-size: 0.85rem;
  font-weight: 700;
  color: #c7d2fe;
}

.oem-fit-tag {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
}

.oem-fit-tag.not-fit {
  background: rgba(239, 68, 68, 0.25);
  color: #fca5a5;
  border: 1px solid rgba(239, 68, 68, 0.4);
}

.oem-fit-tag.fit {
  background: rgba(16, 185, 129, 0.25);
  color: #86efac;
  border: 1px solid rgba(16, 185, 129, 0.4);
}

.header-actions {
  display: flex;
  gap: 0.6rem;
}

/* Tabs */
.view-tabs {
  display: flex;
  gap: 0.5rem;
  margin: 1.5rem 0 1.2rem;
  border-bottom: 1px solid var(--border-subtle);
  padding-bottom: 0.75rem;
}

.tab-btn {
  background: none;
  border: none;
  color: var(--text-muted);
  font-family: var(--font-sans);
  font-size: 0.9rem;
  font-weight: 600;
  padding: 0.4rem 0.9rem;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn:hover {
  color: var(--text-main);
  background: rgba(255, 255, 255, 0.05);
}

.tab-btn.active {
  color: var(--accent-cyan);
  background: var(--badge-bg);
  border: 1px solid var(--badge-border);
}

/* Specs */
.spec-section {
  margin-bottom: 1.75rem;
}

.section-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-main);
  margin-bottom: 0.9rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.spec-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 0.85rem;
}

.spec-item {
  background: rgba(13, 19, 33, 0.6);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  padding: 0.85rem 1rem;
  display: flex;
  flex-direction: column;
  transition: all 0.2s;
}

.spec-item:hover {
  background: rgba(24, 34, 56, 0.7);
  border-color: rgba(255, 255, 255, 0.15);
  transform: translateY(-1px);
}

.spec-label {
  font-size: 0.75rem;
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.spec-value {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text-main);
}

.spec-value.highlight {
  color: var(--accent-cyan);
  font-weight: 700;
}

/* Raw View */
.raw-content {
  margin-top: 1rem;
}

.raw-header {
  font-size: 0.85rem;
  color: var(--text-muted);
  margin-bottom: 0.5rem;
}

.raw-json {
  background: #080c14;
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  padding: 1.25rem;
  font-family: var(--font-mono);
  font-size: 0.85rem;
  color: #38bdf8;
  overflow-x: auto;
  max-height: 420px;
}
</style>
