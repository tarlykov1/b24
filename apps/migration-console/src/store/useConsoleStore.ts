import { create } from 'zustand';

interface ConsoleState {
  selectedJobId?: string;
  fallbackMode: boolean;
  setSelectedJobId: (jobId?: string) => void;
  setFallbackMode: (enabled: boolean) => void;
}

export const useConsoleStore = create<ConsoleState>((set) => ({
  selectedJobId: undefined,
  fallbackMode: false,
  setSelectedJobId: (selectedJobId) => set({ selectedJobId }),
  setFallbackMode: (fallbackMode) => set({ fallbackMode }),
}));
