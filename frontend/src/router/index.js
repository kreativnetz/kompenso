import { createRouter, createWebHistory } from 'vue-router'
import { api } from '../api'
import { clearToken, getToken, getUser, setUser } from '../lib/auth'

const LoginView = () => import('../views/LoginView.vue')
const HomeView = () => import('../views/HomeView.vue')
const TeachersAdminView = () => import('../views/TeachersAdminView.vue')
const ThesisSessionsAdminView = () => import('../views/ThesisSessionsAdminView.vue')
const SchoolyearsAdminView = () => import('../views/SchoolyearsAdminView.vue')
const ThesisSubmitView = () => import('../views/ThesisSubmitView.vue')
const ThesisTeacherBoardView = () => import('../views/ThesisTeacherBoardView.vue')
const ThesisMyBookingsView = () => import('../views/ThesisMyBookingsView.vue')
const ThesisSupervisionListView = () => import('../views/ThesisSupervisionListView.vue')

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { public: true },
    },
    {
      path: '/',
      name: 'home',
      component: HomeView,
      meta: { public: true },
    },
    {
      path: '/thema/einreichen',
      name: 'thesis-submit',
      component: ThesisSubmitView,
      meta: { public: true },
    },
    {
      path: '/lehrpersonen',
      name: 'teachers',
      component: TeachersAdminView,
      meta: { requiresManager: true },
    },
    {
      path: '/schuljahre',
      name: 'schoolyears',
      component: SchoolyearsAdminView,
      meta: { requiresManager: true },
    },
    {
      path: '/zuordnungssessions',
      name: 'thesis-sessions',
      component: ThesisSessionsAdminView,
      meta: { requiresManager: true },
    },
    {
      path: '/arbeiten',
      name: 'thesis-teacher-board',
      component: ThesisTeacherBoardView,
    },
    {
      path: '/arbeiten/buchungen',
      name: 'thesis-my-bookings',
      component: ThesisMyBookingsView,
    },
    {
      path: '/arbeiten/betreuungsliste',
      name: 'thesis-supervision-list',
      component: ThesisSupervisionListView,
    },
  ],
})

router.beforeEach(async (to) => {
  const token = getToken()

  if (to.meta.public) {
    if (to.name === 'login' && token) {
      return { name: 'home' }
    }
    return true
  }

  if (!token) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (
    to.name === 'thesis-teacher-board' ||
    to.name === 'thesis-my-bookings' ||
    to.name === 'thesis-supervision-list'
  ) {
    const q = to.query.thesis_session_id
    if (q == null || String(q).trim() === '') {
      return { name: 'home', query: { board_missing: '1' } }
    }
  }

  if (to.meta.requiresManager) {
    let user = getUser()
    if (!user?.abilities) {
      const res = await api.me()
      if (!res.ok) {
        clearToken()
        return { name: 'login', query: { redirect: to.fullPath } }
      }
      const data = await res.json()
      user = data.teacher
      setUser(user)
    }
    if (!user.abilities?.manage_teachers) {
      return { name: 'home' }
    }
  }

  return true
})

export default router
