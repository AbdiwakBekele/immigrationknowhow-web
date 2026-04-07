<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex">
        <!-- Left Panel - Branding -->
        <div class="hidden lg:flex lg:w-1/2 xl:w-2/5 relative overflow-hidden">
            <!-- Background with gradient -->
            <div class="absolute inset-0 bg-gradient-to-br from-sky-600 via-indigo-600 to-violet-700"></div>
            
            <!-- Pattern overlay -->
            <div class="absolute inset-0 opacity-10">
                <svg class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)"/>
                </svg>
            </div>

            <!-- Content -->
            <div class="relative z-10 flex flex-col justify-between p-12 text-white">
                <!-- Logo -->
                <div>
                    <Link href="/" class="flex items-center space-x-3">
                        <div class="h-12 w-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                            <span class="text-white font-bold text-xl">IK</span>
                        </div>
                        <span class="text-2xl font-bold">ImmigrationKnowHow</span>
                    </Link>
                </div>

                <!-- Main message -->
                <div class="space-y-6">
                    <h1 class="text-4xl xl:text-5xl font-bold leading-tight">
                        Your Journey to a<br />
                        <span class="text-sky-200">New Beginning</span><br />
                        Starts Here
                    </h1>
                    <p class="text-lg text-sky-100 max-w-md">
                        Connect with trusted immigration attorneys, accountants, tutors, and more. 
                        Get the professional support you need to thrive in your new home.
                    </p>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-6">
                    <div>
                        <div class="text-3xl font-bold">500+</div>
                        <div class="text-sm text-sky-200">Verified Providers</div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold">50+</div>
                        <div class="text-sm text-sky-200">Languages</div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold">10k+</div>
                        <div class="text-sm text-sky-200">Happy Users</div>
                    </div>
                </div>
            </div>

            <!-- Decorative circles -->
            <div class="absolute -bottom-32 -left-32 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute -top-32 -right-32 h-64 w-64 rounded-full bg-violet-500/30 blur-3xl"></div>
        </div>

        <!-- Right Panel - Form -->
        <div class="flex-1 flex flex-col">
            <!-- Mobile header -->
            <div class="lg:hidden p-6 flex justify-between items-center border-b border-slate-100">
                <Link href="/" class="flex items-center space-x-2">
                    <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-sky-500 to-indigo-600 flex items-center justify-center">
                        <span class="text-white font-bold text-lg">IK</span>
                    </div>
                    <span class="text-xl font-semibold text-slate-900">ImmigrationKnowHow</span>
                </Link>
            </div>

            <!-- Form container -->
            <div class="flex-1 flex items-center justify-center p-6 sm:p-12">
                <div class="w-full max-w-md">
                    <slot />
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 text-center text-sm text-slate-500">
                <p>© {{ new Date().getFullYear() }} ImmigrationKnowHow. All rights reserved.</p>
            </div>
        </div>
    </div>
</template>
