<template>
  <div class="flex flex-col h-screen bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-white transition-colors duration-300">
    <!-- 顶部导航栏 -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-50 transition-all duration-300">
      <div class="container mx-auto px-4 py-3 flex justify-between items-center">
        <div class="flex items-center gap-2">
          <div class="bg-gradient-to-r from-primary to-secondary p-2 rounded-lg">
            <i class="fa fa-comments text-white text-xl"></i>
          </div>
          <h1 class="text-xl font-bold bg-gradient-to-r from-primary to-secondary text-gradient">AI流式聊天助手</h1>
        </div>
        <div class="flex items-center gap-3">
          <button @click="toggleTheme" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
            <i class="fa" :class="isDarkMode ? 'fa-sun-o text-yellow-400' : 'fa-moon-o text-gray-600'"></i>
          </button>
          <button @click="clearChatHistory" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
            <i class="fa fa-trash-o text-gray-600 dark:text-gray-300"></i>
          </button>
        </div>
      </div>
    </header>

    <!-- 主要内容区域 -->
    <main class="flex-1 container mx-auto px-4 py-6 flex flex-col max-w-4xl">
      <!-- 聊天历史区域 -->
      <div id="chat-history" class="flex-1 overflow-y-auto scrollbar-hide mb-6 space-y-6 pb-4">
        <!-- 欢迎消息 -->
        <div class="flex items-start gap-3 animate-fadeIn" v-if="messages.length === 0">
          <div class="bg-gradient-to-r from-primary to-secondary text-white p-2 rounded-full shrink-0">
            <i class="fa fa-robot"></i>
          </div>
          <div class="bg-ai-message dark:bg-gray-700 rounded-2xl px-4 py-3 max-w-[85%] shadow-sm">
            <p>你好！我是AI助手，我可以实时流式回答你的问题。请输入你的问题，我会立即开始回复。</p>
          </div>
        </div>

        <!-- 聊天消息列表 -->
        <div 
          v-for="(message, index) in messages" 
          :key="index" 
          class="flex items-start gap-3 animate-fadeIn"
        >
          <!-- 用户消息 -->
          <template v-if="message.role === 'user'">
            <div class="bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-white p-2 rounded-full shrink-0">
              <i class="fa fa-user"></i>
            </div>
            <div class="bg-user-message dark:bg-gray-700 rounded-2xl px-4 py-3 max-w-[85%] shadow-sm ml-auto">
              <p v-html="formatMessage(message.content)"></p>
            </div>
          </template>

          <!-- AI消息 -->
          <template v-if="message.role === 'ai'">
            <div class="bg-gradient-to-r from-primary to-secondary text-white p-2 rounded-full shrink-0">
              <i class="fa fa-robot"></i>
            </div>
            <div class="bg-ai-message dark:bg-gray-700 rounded-2xl px-4 py-3 max-w-[85%] shadow-sm">
              <p v-html="formatMessage(message.content)" class="whitespace-pre-wrap"></p>
            </div>
          </template>
        </div>

        <!-- 正在输入指示器 -->
        <div v-if="isLoading" class="flex items-start gap-3 animate-fadeIn">
          <div class="bg-gradient-to-r from-primary to-secondary text-white p-2 rounded-full shrink-0">
            <i class="fa fa-robot"></i>
          </div>
          <div class="bg-ai-message dark:bg-gray-700 rounded-2xl px-4 py-3 max-w-[85%] shadow-sm">
            <div class="typing-indicator">
              <span></span>
              <span></span>
              <span></span>
            </div>
          </div>
        </div>
      </div>

      <!-- 输入区域 -->
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md p-3 mb-6 transition-all duration-300">
        <form @submit.prevent="handleSubmit" class="flex flex-col gap-3">
          <textarea 
            v-model="userInput"
            placeholder="输入你的问题..." 
            class="w-full border border-gray-200 dark:border-gray-700 bg-transparent rounded-xl p-3 focus:outline-none focus:ring-2 focus:ring-primary/50 resize-none transition-all min-h-[80px] max-h-[200px] overflow-y-auto"
            @input="adjustTextareaHeight"
          ></textarea>
          <div class="flex justify-between items-center">
            <div class="flex gap-2">
              <button type="button" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-300">
                <i class="fa fa-paperclip"></i>
              </button>
              <button type="button" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-300">
                <i class="fa fa-microphone"></i>
              </button>
            </div>
            <button 
              type="submit" 
              :disabled="!userInput.trim() || isLoading"
              class="bg-gradient-to-r from-primary to-secondary text-white px-6 py-2 rounded-full font-medium hover:opacity-90 transition-opacity flex items-center gap-2 disabled:opacity-50"
            >
              <span>发送</span>
              <i class="fa fa-paper-plane"></i>
            </button>
          </div>
        </form>
      </div>
    </main>

    <!-- 页脚 -->
    <footer class="bg-white dark:bg-gray-800 py-4 border-t border-gray-200 dark:border-gray-700">
      <div class="container mx-auto px-4 text-center text-gray-500 dark:text-gray-400 text-sm">
        <p>AI流式聊天助手 &copy; 2023 | 实时响应，智能交互</p>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';

// 状态管理
const userInput = ref('');
const messages = ref([]);
const isLoading = ref(false);
const isDarkMode = ref(false);

// 示例回复数据 - 实际应用中会从API获取
const sampleResponses = {
  "你好": "你好！很高兴见到你。我是一个AI助手，可以帮助你解答各种问题。无论你有什么疑问，都可以问我，我会尽力为你提供帮助。如果你想了解我的功能，也可以随时问我哦！",
  "什么是人工智能": "人工智能（Artificial Intelligence，简称AI）是计算机科学的一个分支，它致力于创造能够模拟人类智能的系统。人工智能的研究包括机器学习、自然语言处理、计算机视觉、机器人技术等多个领域。\n\n随着技术的发展，人工智能已经广泛应用于我们的日常生活中，比如语音助手、推荐系统、自动驾驶等。人工智能的目标是让机器能够像人类一样思考、学习和解决问题，从而提高工作效率，改善生活质量。",
  "如何学习编程": "学习编程是一个循序渐进的过程，以下是一些建议可以帮助你开始：\n\n1. 选择一门入门语言：如Python（适合初学者）、JavaScript（网页开发）或Java（应用广泛）。\n2. 学习基础概念：变量、数据类型、循环、条件语句、函数等。\n3. 实践项目：通过实际项目练习，将理论知识应用到实践中。\n4. 学习调试：学会阅读错误信息并解决问题。\n5. 加入社区：与其他开发者交流，参与开源项目。\n6. 持续学习：编程技术不断发展，保持学习的热情和习惯。\n\n最重要的是坚持实践，编程是一门需要不断练习的技能。",
  "推荐一些好书": "以下是一些不同领域的经典书籍推荐：\n\n1. 文学类：《百年孤独》（加西亚·马尔克斯）、《活着》（余华）\n2. 科幻类：《三体》（刘慈欣）、《沙丘》（弗兰克·赫伯特）\n3. 心理学：《影响力》（罗伯特·西奥迪尼）、《思考，快与慢》（丹尼尔·卡尼曼）\n4. 商业类：《穷查理宝典》（彼得·考夫曼）、《原则》（瑞·达利欧）\n5. 自我成长：《原子习惯》（詹姆斯·克利尔）、《高效能人士的七个习惯》（史蒂芬·柯维）\n\n这些书籍在各自领域都广受好评，希望能有你感兴趣的。"
};

// 初始化
onMounted(() => {
  // 检查系统主题偏好
  if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    toggleTheme();
  }
  
  adjustTextareaHeight();
});

// 调整文本框高度
const adjustTextareaHeight = () => {
  nextTick(() => {
    const textarea = document.querySelector('textarea');
    if (textarea) {
      textarea.style.height = 'auto';
      textarea.style.height = (textarea.scrollHeight > 200 ? 200 : textarea.scrollHeight) + 'px';
    }
  });
};

// 处理发送消息
const handleSubmit = () => {
  const message = userInput.value.trim();
  if (!message || isLoading.value) return;
  
  // 添加用户消息
  messages.value.push({ role: 'user', content: message });
  userInput.value = '';
  adjustTextareaHeight();
  scrollToBottom();
  
  // 显示加载状态
  isLoading.value = true;
  
  // 模拟API请求延迟
  setTimeout(() => {
    // 获取回复内容
    const response = sampleResponses[message] || 
      `你问的是："${message}"。这是一个很好的问题！我可以为你提供相关的信息和解答。如果你有更具体的需求或问题，可以进一步告诉我，我会尽力帮助你。`;
    
    // 添加AI消息占位符
    messages.value.push({ role: 'ai', content: '' });
    isLoading.value = false;
    scrollToBottom();
    
    // 流式输出回复
    streamResponse(response, messages.value.length - 1);
  }, 800);
};

// 流式输出AI回复
const streamResponse = (text, index) => {
  let charIndex = 0;
  const speed = 15; // 打字速度，毫秒
  
  const typeInterval = setInterval(() => {
    if (charIndex < text.length) {
      // 处理换行符
      if (text[charIndex] === '\n') {
        messages.value[index].content += '\n';
      } else {
        messages.value[index].content += text[charIndex];
      }
      charIndex++;
      scrollToBottom();
    } else {
      clearInterval(typeInterval);
    }
  }, speed);
};

// 格式化消息内容
const formatMessage = (content) => {
  return content
    .replace(/\n/g, '<br>')
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>');
};

// 滚动到聊天底部
const scrollToBottom = () => {
  nextTick(() => {
    const chatHistory = document.getElementById('chat-history');
    if (chatHistory) {
      chatHistory.scrollTop = chatHistory.scrollHeight;
    }
  });
};

// 切换主题
const toggleTheme = () => {
  isDarkMode.value = !isDarkMode.value;
  document.documentElement.classList.toggle('dark');
};

// 清空聊天记录
const clearChatHistory = () => {
  if (confirm('确定要清空所有聊天记录吗？')) {
    messages.value = [];
  }
};
</script>

<style scoped>
@layer utilities {
  .content-auto {
    content-visibility: auto;
  }
  .scrollbar-hide {
    scrollbar-width: none;
    -ms-overflow-style: none;
  }
  .scrollbar-hide::-webkit-scrollbar {
    display: none;
  }
  .text-gradient {
    background-clip: text;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }
  .typing-indicator {
    display: inline-flex;
    align-items: center;
    gap: 3px;
  }
  .typing-indicator span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #6B7280;
    animation: typing 1.4s infinite ease-in-out both;
  }
  .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
  .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
  @keyframes typing {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
  }
  .animate-fadeIn {
    animation: fadeIn 0.3s ease-in-out;
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }
}

/* 全局样式变量 */
:root {
  --primary: #3B82F6;
  --secondary: #6366F1;
  --neutral: #F3F4F6;
  --neutral-dark: #1F2937;
  --ai-message: #EFF6FF;
  --user-message: #E0E7FF;
}

.dark {
  --ai-message: #1F2937;
  --user-message: #374151;
}
</style>
