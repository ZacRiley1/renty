import { createRouter, createWebHistory } from 'vue-router'
import Home from './views/Home.vue'
import Dashboard from './views/Dashboard.vue'

export default createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: Home },
    { path: '/dashboard', name: 'dashboard', component: Dashboard },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})