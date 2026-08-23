import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { App } from '@/App';
import { AuthProvider } from '@/hooks/useAuth';
import { OperatorAuthProvider } from '@/hooks/useOperatorAuth';

const root = document.getElementById('root');

if (root) {
    createRoot(root).render(
        <StrictMode>
            <BrowserRouter>
                <AuthProvider>
                    <OperatorAuthProvider>
                        <App />
                    </OperatorAuthProvider>
                </AuthProvider>
            </BrowserRouter>
        </StrictMode>,
    );
}
