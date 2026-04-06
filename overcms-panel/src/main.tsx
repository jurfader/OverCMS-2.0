import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import './styles/globals.css';

const target = document.getElementById('overcms-root');
if (target) {
  createRoot(target).render(
    <StrictMode>
      <App />
    </StrictMode>,
  );
}
