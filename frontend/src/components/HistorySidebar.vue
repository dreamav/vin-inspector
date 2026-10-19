<template>
  <div class="history-panel glass-panel">
    <div class="history-header">
      <div class="history-title">
        <span class="icon">🕒</span>
        <h3>История проверок</h3>
      </div>
      <button 
        v-if="history.length > 0" 
        type="button" 
        class="clear-history-btn" 
        @click="$emit('clear')"
        title="Очистить историю"
      >
        Очистить
      </button>
    </div>

    <div v-if="history.length === 0" class="empty-history">
      <p>Пока нет проверенных номеров</p>
      <span>История последних запросов будет сохраняться здесь</span>
    </div>

    <ul v-else class="history-list">
      <li 
        v-for="item in history" 
        :key="item.vin" 
        class="history-item"
        :class="{ active: currentVin === item.vin }"
        @click="$emit('select', item)"
      >
        <div class="item-main">
          <span class="item-vin">{{ item.vin }}</span>
          <span v-if="item.oem" class="item-oem" :class="'status-' + (item.data && item.data.oemStatus)">
            OEM: {{ item.oem }}
            <span v-if="item.data && item.data.oemStatus === 'fit'" class="status-badge-inline fit">✓</span>
            <span v-else-if="item.data && item.data.oemStatus === 'not-fit'" class="status-badge-inline not-fit">✕</span>
          </span>
          <span class="item-car">{{ item.title || 'Автомобиль' }}</span>
        </div>
        <div class="item-meta">
          <span class="item-time">{{ formatTime(item.timestamp) }}</span>
          <button 
            type="button" 
            class="item-delete" 
            @click.stop="$emit('remove', item.vin)"
            title="Удалить из истории"
          >
            ✕
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>

<script>
export default {
  name: 'HistorySidebar',
  props: {
    history: {
      type: Array,
      default: () => []
    },
    currentVin: {
      type: String,
      default: ''
    }
  },
  methods: {
    formatTime(timestamp) {
      if (!timestamp) return '';
      const date = new Date(timestamp);
      return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
  }
};
</script>

<style scoped>
.history-panel {
  padding: 1.5rem;
  height: 100%;
}

.history-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border-subtle);
}

.history-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.history-title h3 {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-main);
}

.clear-history-btn {
  background: none;
  border: none;
  color: var(--text-dim);
  font-size: 0.8rem;
  cursor: pointer;
  padding: 0.2rem 0.4rem;
  border-radius: 4px;
  transition: color 0.2s;
}

.clear-history-btn:hover {
  color: var(--danger);
}

.empty-history {
  padding: 2rem 1rem;
  text-align: center;
  color: var(--text-dim);
  font-size: 0.85rem;
}

.empty-history span {
  display: block;
  font-size: 0.75rem;
  margin-top: 0.3rem;
  opacity: 0.7;
}

.history-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  max-height: 480px;
  overflow-y: auto;
  padding-right: 0.25rem;
}

.history-item {
  background: rgba(13, 19, 33, 0.6);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  padding: 0.75rem 0.9rem;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: all 0.2s;
}

.history-item:hover {
  background: rgba(24, 34, 56, 0.8);
  border-color: rgba(56, 189, 248, 0.3);
  transform: translateX(2px);
}

.history-item.active {
  background: rgba(56, 189, 248, 0.12);
  border-color: var(--accent-cyan);
}

.item-main {
  display: flex;
  flex-direction: column;
}

.item-vin {
  font-family: var(--font-mono);
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--accent-cyan);
  letter-spacing: 0.05em;
}

.item-oem {
  font-family: var(--font-mono);
  font-size: 0.72rem;
  color: #a5b4fc;
  letter-spacing: 0.04em;
  margin-top: 0.1rem;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.item-oem.status-not-fit {
  color: #fca5a5;
}

.item-oem.status-fit {
  color: #86efac;
}

.status-badge-inline {
  font-size: 0.65rem;
  font-weight: 800;
  padding: 0.05rem 0.3rem;
  border-radius: 3px;
  line-height: 1;
}

.status-badge-inline.not-fit {
  background: rgba(239, 68, 68, 0.25);
  color: #f87171;
}

.status-badge-inline.fit {
  background: rgba(16, 185, 129, 0.25);
  color: #34d399;
}

.item-car {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin-top: 0.15rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 170px;
}

.item-meta {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.item-time {
  font-size: 0.75rem;
  color: var(--text-dim);
}

.item-delete {
  background: none;
  border: none;
  color: var(--text-dim);
  font-size: 0.8rem;
  cursor: pointer;
  padding: 0.2rem;
  border-radius: 4px;
  opacity: 0;
  transition: opacity 0.2s, color 0.2s;
}

.history-item:hover .item-delete {
  opacity: 1;
}

.item-delete:hover {
  color: var(--danger);
}
</style>
