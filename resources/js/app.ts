import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import FarmManagementLayout from '@/layouts/FarmManagementLayout.vue';
import UserManagementLayout from '@/layouts/UserManagementLayout.vue';
import LogbookLayout from '@/layouts/LogbookLayout.vue';
import SopManagementLayout from '@/layouts/SopManagementLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PetugasLayout from '@/layouts/PetugasLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import OwnerLayout from '@/layouts/OwnerLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { getAppName, resolveAppName } from '@/lib/appName';
import { dismissStartupScreen, showStartupError } from '@/lib/startupScreen';

void createInertiaApp({
    title: (title, page) => {
        const appName = resolveAppName(page.props.name);
        return title ? `${title} - ${appName}` : appName;
    },
    layout: (name) => {
        switch (true) {
            // The same /settings/profile page supplies its own role-specific
            // shell. Petugas receives the Farmer/mobile surface; Owner,
            // Admin and Superadmin share the sidebar settings shell.
            case name === 'settings/Profile':
            case name === 'settings/Security':
                return null;
            case name.startsWith('owner/'):
                return OwnerLayout;
            case name.startsWith('petugas/'):
                return PetugasLayout;
            case name === 'Welcome':
            case name.startsWith('farmer/'):
            case name.startsWith('errors/'):
                return null;
            case name.startsWith('farms/'):
                return FarmManagementLayout;
            case name.startsWith('sops/'):
                return SopManagementLayout;
            case name.startsWith('users/'):
                return UserManagementLayout;
            case name.startsWith('logbook/'):
                return LogbookLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
            case name.startsWith('teams/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#4B5563',
    },
})
    .then(dismissStartupScreen)
    .catch((error: unknown) => {
        showStartupError();
        console.error(`${getAppName()} could not start:`, error);
    });

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
