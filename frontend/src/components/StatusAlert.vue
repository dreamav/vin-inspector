<template>
  <div v-if="message" class="status-alert" :class="typeClass">
    <span class="alert-icon">{{ icon }}</span>
    <div class="alert-content">
      <h4 v-if="title" class="alert-title">{{ title }}</h4>
      <p class="alert-message">{{ message }}</p>
    </div>
    <button 
      v-if="dismissible" 
      type="button" 
      class="alert-close" 
      @click="$emit('dismiss')"
    >
      ✕
    </button>
  </div>
</template>

<script>
export default {
  name: 'StatusAlert',
  props: {
    type: {
      type: String,
      default: 'info', // 'error', 'warning', 'info', 'success'
      validator: (val) => ['error', 'warning', 'info', 'success'].includes(val)
    },
    title: {
      type: String,
      default: ''
    },
    message: {
      type: String,
      default: ''
    },
    dismissible: {
      type: Boolean,
      default: true
    }
  },
  computed: {
    typeClass() {
      return `alert-${this.type}`;
    },
    icon() {
      switch (this.type) {
        case 'error': return '❌';
        case 'warning': return '⚠️';
        case 'success': return '✅';
        case 'info':
        default: return 'ℹ️';
      }
    }
  }
};
</script>

<style scoped>
.status-alert {
  display: flex;
  align-items: flex-start;
  gap: 0.9rem;
  padding: 1rem 1.25rem;
  border-radius: var(--radius-sm);
  margin-bottom: 1.5rem;
  border: 1px solid transparent;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-6px); }
  to { opacity: 1; transform: translateY(0); }
}

.alert-error {
  background: rgba(239, 68, 68, 0.12);
  border-color: rgba(239, 68, 68, 0.3);
  color: #fecaca;
}

.alert-warning {
  background: rgba(245, 158, 11, 0.12);
  border-color: rgba(245, 158, 11, 0.3);
  color: #fde68a;
}

.alert-success {
  background: rgba(16, 185, 129, 0.12);
  border-color: rgba(16, 185, 129, 0.3);
  color: #a7f3d0;
}

.alert-info {
  background: rgba(56, 189, 248, 0.12);
  border-color: rgba(56, 189, 248, 0.3);
  color: #bae6fd;
}

.alert-icon {
  font-size: 1.2rem;
  line-height: 1;
}

.alert-content {
  flex: 1;
}

.alert-title {
  font-size: 0.95rem;
  font-weight: 700;
  margin-bottom: 0.2rem;
}

.alert-message {
  font-size: 0.88rem;
  line-height: 1.4;
  opacity: 0.95;
}

.alert-close {
  background: none;
  border: none;
  color: inherit;
  font-size: 0.85rem;
  cursor: pointer;
  opacity: 0.6;
  padding: 0.2rem;
  transition: opacity 0.2s;
}

.alert-close:hover {
  opacity: 1;
}
</style>
