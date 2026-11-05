        <div class="bg-white dark:bg-dark-700 rounded-lg shadow mb-4 p-4">
            <div class="stories-section-wrapper relative group">
                <button id="scrollStoriesLeft" aria-label="Scroll stories left"
                    class="absolute left-2 top-1/2 -translate-y-1/2 z-30 p-2 bg-white dark:bg-dark-600 bg-opacity-75 dark:bg-opacity-85 rounded-full shadow-lg text-gray-700 dark:text-gray-200 hover:bg-opacity-100 dark:hover:bg-dark-500 focus:outline-none focus:ring-2 focus:ring-blue-500 opacity-0 group-hover:opacity-100 transition-all duration-300 hidden">
                    <i class="fas fa-chevron-left fa-fw"></i>
                </button>

                <div id="stories-items-container" class="flex items-center space-x-3 overflow-x-auto py-2 scrollbar-hide">
                    <div id="create-story-button-static"
                        class="flex-shrink-0 relative w-28 h-44 md:w-32 md:h-48 rounded-xl overflow-hidden bg-gray-100 dark:bg-dark-600 cursor-pointer story-item create-story-item border border-transparent hover:border-blue-500 dark:hover:border-blue-400 transition-all duration-200"
                        role="button" tabindex="0" aria-label="Create a new story">
                        <div class="w-full h-2/3 overflow-hidden">
                            <img src="https://via.placeholder.com/128x128/e2e8f0/9ca3af?text=+" alt="Your avatar"
                                class="w-full h-full object-cover user-avatar-for-create">
                        </div>
                        <div class="absolute top-2 left-1/2 -translate-x-1/2 md:top-3 md:left-3 md:transform-none w-8 h-8 md:w-10 md:h-10 rounded-full border-2 md:border-4 border-blue-500 bg-white dark:bg-dark-700 flex items-center justify-center shadow">
                            <i class="fas fa-plus text-blue-500"></i>
                        </div>
                        <div class="h-1/3 flex items-center justify-center p-1 md:p-2 text-center bg-gray-50 dark:bg-dark-550">
                            <p class="font-semibold text-xs md:text-sm text-gray-700 dark:text-white">Create Story</p>
                        </div>
                    </div>

                    <div class="placeholder-story-item flex-shrink-0 relative w-28 h-44 md:w-32 md:h-48 rounded-xl overflow-hidden bg-gray-300 dark:bg-dark-500">
                        <img src="https://picsum.photos/seed/story1/300/500" alt="Story placeholder"
                            class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                        <div class="absolute top-2 left-2 w-7 h-7 md:w-8 md:h-8 rounded-full border-2 border-blue-500 p-0.5 bg-white dark:bg-dark-700">
                            <img src="https://i.pravatar.cc/40?u=jane" alt="User placeholder"
                                class="w-full h-full rounded-full object-cover">
                        </div>
                        <div class="absolute bottom-2 left-2 right-2 text-white">
                            <p class="font-semibold text-xs md:text-sm truncate">Jane Smith</p>
                        </div>
                    </div>

                    <div class="placeholder-story-item flex-shrink-0 relative w-28 h-44 md:w-32 md:h-48 rounded-xl overflow-hidden bg-gray-300 dark:bg-dark-500">
                        <img src="https://picsum.photos/seed/story2/300/500" alt="Story placeholder"
                            class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                        <div class="absolute top-2 left-2 w-7 h-7 md:w-8 md:h-8 rounded-full border-2 border-blue-500 p-0.5 bg-white dark:bg-dark-700">
                            <img src="https://i.pravatar.cc/40?u=mike" alt="User placeholder"
                                class="w-full h-full rounded-full object-cover">
                        </div>
                        <div class="absolute bottom-2 left-2 right-2 text-white">
                            <p class="font-semibold text-xs md:text-sm truncate">Mike Johnson</p>
                        </div>
                    </div>

                    <div class="placeholder-story-item flex-shrink-0 relative w-28 h-44 md:w-32 md:h-48 rounded-xl overflow-hidden bg-gray-300 dark:bg-dark-500">
                        <img src="https://picsum.photos/seed/story3/300/500" alt="Story placeholder"
                            class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                        <div class="absolute top-2 left-2 w-7 h-7 md:w-8 md:h-8 rounded-full border-2 border-blue-500 p-0.5 bg-white dark:bg-dark-700">
                            <img src="https://i.pravatar.cc/40?u=sarah" alt="User placeholder"
                                class="w-full h-full rounded-full object-cover">
                        </div>
                        <div class="absolute bottom-2 left-2 right-2 text-white">
                            <p class="font-semibold text-xs md:text-sm truncate">Sarah Williams</p>
                        </div>
                    </div>
                </div>

                <button id="scrollStoriesRight" aria-label="Scroll stories right"
                    class="absolute right-2 top-1/2 -translate-y-1/2 z-30 p-2 bg-white dark:bg-dark-600 bg-opacity-75 dark:bg-opacity-85 rounded-full shadow-lg text-gray-700 dark:text-gray-200 hover:bg-opacity-100 dark:hover:bg-dark-500 focus:outline-none focus:ring-2 focus:ring-blue-500 opacity-0 group-hover:opacity-100 transition-all duration-300 hidden">
                    <i class="fas fa-chevron-right fa-fw"></i>
                </button>
            </div>
        </div>
