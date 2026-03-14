import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Layout } from './components/Layout';
import {
  ConflictsPage,
  DashboardPage,
  DiffPage,
  GraphPage,
  HeatmapPage,
  HealthPage,
  IntegrityPage,
  JobsPage,
  LogsPage,
  MappingPage,
  ReplayPage,
  WorkersPage,
  SecurityHubPage,
  RoleMatrixPage,
  ApprovalQueuePage,
  AuditExplorerPage,
  SessionSecurityPage,
  PolicySimulatorPage,
  IncidentReviewPage,
} from './pages/pages';
import './styles/app.css';

const queryClient = new QueryClient();

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<Layout />}>
            <Route index element={<DashboardPage />} />
            <Route path="jobs" element={<JobsPage />} />
            <Route path="graph" element={<GraphPage />} />
            <Route path="heatmap" element={<HeatmapPage />} />
            <Route path="mapping" element={<MappingPage />} />
            <Route path="workers" element={<WorkersPage />} />
            <Route path="logs" element={<LogsPage />} />
            <Route path="conflicts" element={<ConflictsPage />} />
            <Route path="integrity" element={<IntegrityPage />} />
            <Route path="diff" element={<DiffPage />} />
            <Route path="replay" element={<ReplayPage />} />
            <Route path="health" element={<HealthPage />} />
            <Route path="security" element={<SecurityHubPage />} />
            <Route path="security/roles" element={<RoleMatrixPage />} />
            <Route path="security/approvals" element={<ApprovalQueuePage />} />
            <Route path="security/audit" element={<AuditExplorerPage />} />
            <Route path="security/sessions" element={<SessionSecurityPage />} />
            <Route path="security/policy" element={<PolicySimulatorPage />} />
            <Route path="security/incidents" element={<IncidentReviewPage />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  </React.StrictMode>,
);
