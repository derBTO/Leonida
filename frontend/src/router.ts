import { createRouter, createWebHistory } from 'vue-router'
import MapPage from './views/MapPage.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'map', component: MapPage },
    { path: '/pin/:id(\\d+)', name: 'pin', component: MapPage },
    { path: '/waffen', component: () => import('./views/CatalogPage.vue'), props: { kind: 'weapons' } },
    { path: '/waffen/:id(\\d+)', component: () => import('./views/CatalogDetail.vue'), props: (r) => ({ kind: 'weapons', id: r.params.id }) },
    { path: '/fahrzeuge', component: () => import('./views/CatalogPage.vue'), props: { kind: 'vehicles' } },
    { path: '/fahrzeuge/:id(\\d+)', component: () => import('./views/CatalogDetail.vue'), props: (r) => ({ kind: 'vehicles', id: r.params.id }) },
    { path: '/spuren', component: () => import('./views/TrailsPage.vue') },
    { path: '/spuren/:id(\\d+)', component: () => import('./views/TrailDetail.vue'), props: true },
    { path: '/admin', component: () => import('./views/AdminPage.vue'), meta: { admin: true } },
    { path: '/quellen', component: () => import('./views/SourcesPage.vue') },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})
