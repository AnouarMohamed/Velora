import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AppBackground } from './components/AppBackground';
import { AuthProvider } from './context/AuthContext';
import { Layout } from './components/Layout';
import { ProtectedRoute } from './components/ProtectedRoute';
import { HomePage } from './pages/HomePage';
import { LoginPage } from './pages/LoginPage';
import { RegisterPage } from './pages/RegisterPage';
import { RequestEventPage } from './pages/RequestEventPage';
import { DashboardPage } from './pages/DashboardPage';
import { BrowseEventsPage } from './pages/participant/BrowseEventsPage';
import { EventDetailPage } from './pages/participant/EventDetailPage';
import { MyRegistrationsPage } from './pages/participant/MyRegistrationsPage';
import { OrganizerEventsPage } from './pages/organizer/OrganizerEventsPage';
import { OrganizerEventDetailPage } from './pages/organizer/OrganizerEventDetailPage';
import { CreateEventPage } from './pages/organizer/CreateEventPage';
import { AdminDashboardPage } from './pages/admin/AdminDashboardPage';
import { AdminRequestsPage } from './pages/admin/AdminRequestsPage';
import { AdminEventsPage } from './pages/admin/AdminEventsPage';
import { AdminUsersPage } from './pages/admin/AdminUsersPage';
import { AdminMyEventsPage } from './pages/admin/AdminMyEventsPage';
import { AdminOrganizerSpacePage } from './pages/admin/AdminOrganizerSpacePage';
import { ClientStatsPage } from './pages/client/ClientStatsPage';
import { ManageRegistrationsPage } from './pages/staff/ManageRegistrationsPage';
function App() {
    return (_jsxs(AuthProvider, { children: [_jsx(AppBackground, {}), _jsx(BrowserRouter, { children: _jsx(Routes, { children: _jsxs(Route, { element: _jsx(Layout, {}), children: [_jsx(Route, { index: true, element: _jsx(HomePage, {}) }), _jsx(Route, { path: "login", element: _jsx(LoginPage, {}) }), _jsx(Route, { path: "register", element: _jsx(RegisterPage, {}) }), _jsx(Route, { path: "dashboard", element: _jsx(ProtectedRoute, { children: _jsx(DashboardPage, {}) }) }), _jsx(Route, { path: "events", element: _jsx(ProtectedRoute, { roles: ['participant'], children: _jsx(BrowseEventsPage, {}) }) }), _jsx(Route, { path: "events/:id", element: _jsx(ProtectedRoute, { roles: ['participant', 'admin', 'organizer', 'client'], children: _jsx(EventDetailPage, {}) }) }), _jsx(Route, { path: "my-registrations", element: _jsx(ProtectedRoute, { roles: ['participant'], children: _jsx(MyRegistrationsPage, {}) }) }), _jsx(Route, { path: "organizer/events", element: _jsx(ProtectedRoute, { roles: ['organizer', 'admin'], children: _jsx(OrganizerEventsPage, {}) }) }), _jsx(Route, { path: "organizer/events/new", element: _jsx(ProtectedRoute, { roles: ['organizer', 'admin'], children: _jsx(CreateEventPage, {}) }) }), _jsx(Route, { path: "organizer/events/:id", element: _jsx(ProtectedRoute, { roles: ['organizer', 'admin'], children: _jsx(OrganizerEventDetailPage, {}) }) }), _jsx(Route, { path: "organizer/registrations", element: _jsx(ProtectedRoute, { roles: ['organizer'], children: _jsx(ManageRegistrationsPage, {}) }) }), _jsx(Route, { path: "admin", element: _jsx(ProtectedRoute, { roles: ['admin'], children: _jsx(AdminDashboardPage, {}) }) }), _jsx(Route, { path: "admin/requests", element: _jsx(ProtectedRoute, { roles: ['admin'], children: _jsx(AdminRequestsPage, {}) }) }), _jsx(Route, { path: "admin/events", element: _jsx(ProtectedRoute, { roles: ['admin'], children: _jsx(AdminEventsPage, {}) }) }), _jsx(Route, { path: "admin/organizer-events/:id", element: _jsx(ProtectedRoute, { roles: ['admin'], children: _jsx(OrganizerEventDetailPage, {}) }) }), _jsx(Route, { path: "admin/organizer-events", element: _jsx(ProtectedRoute, { roles: ['admin'], children: _jsx(AdminOrganizerSpacePage, {}) }) }), _jsx(Route, { path: "admin/my-events/:id", element: _jsx(ProtectedRoute, { roles: ['admin'], children: _jsx(OrganizerEventDetailPage, {}) }) }), _jsx(Route, { path: "admin/my-events", element: _jsx(ProtectedRoute, { roles: ['admin'], children: _jsx(AdminMyEventsPage, {}) }) }), _jsx(Route, { path: "admin/users", element: _jsx(ProtectedRoute, { roles: ['admin'], children: _jsx(AdminUsersPage, {}) }) }), _jsx(Route, { path: "admin/registrations", element: _jsx(ProtectedRoute, { roles: ['admin'], children: _jsx(ManageRegistrationsPage, {}) }) }), _jsx(Route, { path: "client/request-event", element: _jsx(ProtectedRoute, { roles: ['client'], children: _jsx(RequestEventPage, {}) }) }), _jsx(Route, { path: "client/stats", element: _jsx(ProtectedRoute, { roles: ['client'], children: _jsx(ClientStatsPage, {}) }) }), _jsx(Route, { path: "*", element: _jsx(Navigate, { to: "/", replace: true }) })] }) }) })] }));
}
export default App;
