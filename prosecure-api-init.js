/**
 * ProSecure API Auto-Initialization Script
 * Include this file in HTML pages that need automatic API client setup
 */

// Função para inicializar o cliente JavaScript com tokens da sessão PHP
function initializeAPIClientFromPHP() {
  console.log('Initializing ProSecure API client...');
  
  // Esta função será populada via PHP quando necessário
  if (window.prosecureTokens && window.ProSecureAPI) {
      try {
          window.ProSecureAPI.storeTokens(
              window.prosecureTokens.token,
              window.prosecureTokens.refreshToken
          );
          console.log('✅ API client initialized with PHP session tokens');
          
          // Verificar se os tokens são válidos
          const authStatus = window.ProSecureAPI.getAuthStatus();
          console.log('Auth Status:', authStatus);
          
          if (authStatus.isAuthenticated) {
              console.log('✅ User is authenticated:', authStatus.user.username);
          } else {
              console.warn('⚠️ User tokens present but not authenticated');
          }
          
      } catch (error) {
          console.error('❌ Error initializing API client:', error);
      }
  } else {
      console.log('ℹ️ No tokens available or ProSecureAPI not loaded yet');
  }
}

// Função para verificar status de autenticação automaticamente
async function checkAuthStatus() {
  if (window.ProSecureAPI) {
      try {
          const status = window.ProSecureAPI.getAuthStatus();
          
          // Se o token expirou e não conseguir renovar, redirecionar
          if (status.hasToken && status.tokenExpired && !status.hasRefreshToken) {
              console.warn('⚠️ Token expired and no refresh token available');
              window.location.href = '/users/index.php?err9=1';
              return false;
          }
          
          // Se tem refresh token e o access token expirou, tentar renovar
          if (status.hasToken && status.tokenExpired && status.hasRefreshToken) {
              console.log('🔄 Token expired, attempting to refresh...');
              try {
                  const refreshed = await window.ProSecureAPI.refreshTokens();
                  if (refreshed) {
                      console.log('✅ Token refreshed successfully');
                      return true;
                  } else {
                      console.warn('❌ Token refresh failed');
                      window.location.href = '/users/index.php?err9=1';
                      return false;
                  }
              } catch (error) {
                  console.error('❌ Error refreshing token:', error);
                  window.location.href = '/users/index.php?err9=1';
                  return false;
              }
          }
          
          return status.isAuthenticated;
      } catch (error) {
          console.error('❌ Error checking auth status:', error);
          return false;
      }
  }
  
  return false;
}

// Auto-inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
  console.log('🚀 ProSecure API initialization script loaded');
  
  // Aguardar um pouco para garantir que todos os scripts foram carregados
  setTimeout(() => {
      initializeAPIClientFromPHP();
      checkAuthStatus();
  }, 100);
});

// Verificar autenticação a cada 2 minutos
setInterval(() => {
  checkAuthStatus().catch(error => {
      console.error('❌ Periodic auth check failed:', error);
  });
}, 120000);

// Função utilitária para verificar se a API está pronta
function waitForProSecureAPI(callback, timeout = 5000) {
  const startTime = Date.now();
  
  function checkAPI() {
      if (window.ProSecureAPI) {
          callback();
      } else if (Date.now() - startTime < timeout) {
          setTimeout(checkAPI, 100);
      } else {
          console.error('❌ ProSecureAPI not available after timeout');
      }
  }
  
  checkAPI();
}

// Exportar funções para uso global
window.initializeAPIClientFromPHP = initializeAPIClientFromPHP;
window.checkAuthStatus = checkAuthStatus;
window.waitForProSecureAPI = waitForProSecureAPI;

console.log('📦 ProSecure API initialization script ready');