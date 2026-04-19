<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ref } from 'vue';

const emit = defineEmits<{
    send: [body: string];
    typing: [];
}>();

const { t } = useI18n();
const body = ref('');
const textareaRef = ref<HTMLTextAreaElement | null>(null);

let lastTypingEmit = 0;

function handleInput() {
    autoResize();
    const now = Date.now();
    if (now - lastTypingEmit > 2000) {
        lastTypingEmit = now;
        emit('typing');
    }
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}

function send() {
    const text = body.value.trim();
    if (!text) return;
    emit('send', text);
    body.value = '';
    if (textareaRef.value) {
        textareaRef.value.style.height = 'auto';
    }
}

function autoResize() {
    if (!textareaRef.value) return;
    textareaRef.value.style.height = 'auto';
    textareaRef.value.style.height = Math.min(textareaRef.value.scrollHeight, 120) + 'px';
}
</script>

<template>
    <div class="border-t border-gray-200 dark:border-dark-700 px-4 py-3">
        <div class="flex items-end gap-2">
            <textarea
                ref="textareaRef"
                v-model="body"
                @input="handleInput"
                @keydown="handleKeydown"
                :placeholder="t('chat.type_message')"
                rows="1"
                class="flex-1 resize-none rounded-xl border border-gray-300 dark:border-dark-600 bg-white dark:bg-dark-800 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            ></textarea>
            <button
                @click="send"
                :disabled="!body.trim()"
                class="p-2.5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
                :title="t('chat.send')"
            >
                <i class="pi pi-send text-sm"></i>
            </button>
        </div>
    </div>
</template>
