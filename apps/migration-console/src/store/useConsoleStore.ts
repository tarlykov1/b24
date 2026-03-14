import { create } from 'zustand';

interface ConsoleState {
  selectedJobId?: string;
  fallbackMode: boolean;
  selectedRole: string;
  featureFlags: Record<string, boolean>;
  setSelectedJobId: (jobId?: string) => void;
  setFallbackMode: (enabled: boolean) => void;
  setSelectedRole: (role: string) => void;
  setFeatureFlags: (flags: Record<string, boolean>) => void;
}

export const useConsoleStore = create<ConsoleState>((set) => ({
  selectedJobId: undefined,
  fallbackMode: false,
  selectedRole: 'MigrationOperator',
  featureFlags: {},
  setSelectedJobId: (selectedJobId) => set({ selectedJobId }),
  setFallbackMode: (fallbackMode) => set({ fallbackMode }),
  setSelectedRole: (selectedRole) => set({ selectedRole }),
  setFeatureFlags: (featureFlags) => set({ featureFlags }),
}));
