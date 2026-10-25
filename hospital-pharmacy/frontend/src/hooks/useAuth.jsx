import React, { createContext, useContext, useState, useEffect } from 'react';
import { api } from '../services/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    const saved = localStorage.getItem('nexuscare_user');
    return saved ? JSON.parse(saved) : null;
  });
  const [token, setToken] = useState(() => localStorage.getItem('nexuscare_token') || null);
  const [loading, setLoading] = useState(true);

  // Auto-login default demo user (Hospital Administrator) if not already logged in
  useEffect(() => {
    async function initAuth() {
      if (token) {
        try {
          const me = await api.getMe();
          setUser(me);
          localStorage.setItem('nexuscare_user', JSON.stringify(me));
        } catch (err) {
          console.warn('Session expired, auto-refreshing demo admin session');
          await loginDemo('admin');
        }
      } else {
        await loginDemo('admin');
      }
      setLoading(false);
    }
    initAuth();
  }, []);

  async function login(username, password) {
    const data = await api.login(username, password);
    setToken(data.access_token);
    setUser(data.user);
    localStorage.setItem('nexuscare_token', data.access_token);
    localStorage.setItem('nexuscare_user', JSON.stringify(data.user));
    return data.user;
  }

  async function loginDemo(roleKey) {
    try {
      const creds = {
        admin: { u: 'admin', p: 'password123' },
        pharmacist: { u: 'pharmacist', p: 'password123' },
        inventory: { u: 'inventory', p: 'password123' }
      }[roleKey] || { u: 'admin', p: 'password123' };

      const data = await api.login(creds.u, creds.p);
      setToken(data.access_token);
      setUser(data.user);
      localStorage.setItem('nexuscare_token', data.access_token);
      localStorage.setItem('nexuscare_user', JSON.stringify(data.user));
      return data.user;
    } catch (err) {
      console.error('Demo login failed:', err);
    }
  }

  function logout() {
    setUser(null);
    setToken(null);
    localStorage.removeItem('nexuscare_token');
    localStorage.removeItem('nexuscare_user');
  }

  return (
    <AuthContext.Provider value={{ user, token, loading, login, loginDemo, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
