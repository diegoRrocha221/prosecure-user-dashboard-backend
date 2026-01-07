/**
 * ProSecure API Client - Cliente JavaScript para integração com a API de Pagamentos
 * Versão atualizada com suporte completo ao sistema de autenticação JWT
 */
class ProSecureAPI {
  constructor(baseURL = 'https://pay.prosecurelsp.com/api') {
      this.baseURL = baseURL;
      this.token = this.getStoredToken();
      this.refreshToken = this.getStoredRefreshToken();
      
      // Auto-refresh token se necessário
      this.setupAutoRefresh();
  }

  // =============================================
  // GERENCIAMENTO DE TOKENS
  // =============================================

  getStoredToken() {
      return sessionStorage.getItem('prosecure_token');
  }

  getStoredRefreshToken() {
      return sessionStorage.getItem('prosecure_refresh_token');
  }

  storeTokens(token, refreshToken) {
      this.token = token;
      this.refreshToken = refreshToken;
      sessionStorage.setItem('prosecure_token', token);
      sessionStorage.setItem('prosecure_refresh_token', refreshToken);
      
      // Parse do token para obter data de expiração
      try {
          const payload = JSON.parse(atob(token.split('.')[1]));
          const expiresAt = new Date(payload.exp * 1000);
          sessionStorage.setItem('prosecure_token_expires', expiresAt.toISOString());
          console.log('Token stored, expires at:', expiresAt.toLocaleString());
      } catch (e) {
          console.warn('Could not parse token expiration:', e);
      }
  }

  clearTokens() {
      this.token = null;
      this.refreshToken = null;
      sessionStorage.removeItem('prosecure_token');
      sessionStorage.removeItem('prosecure_refresh_token');
      sessionStorage.removeItem('prosecure_token_expires');
      sessionStorage.removeItem('prosecure_user_info');
  }

  isAuthenticated() {
      return this.token !== null && !this.isTokenExpired();
  }

  isTokenExpired() {
      const expiresAt = sessionStorage.getItem('prosecure_token_expires');
      if (!expiresAt) return true;
      
      return new Date() >= new Date(expiresAt);
  }

  setupAutoRefresh() {
      // Verificar a cada 30 segundos se o token precisa ser renovado
      setInterval(() => {
          if (this.token && this.isTokenExpired() && this.refreshToken) {
              console.log('Token expired, attempting auto-refresh...');
              this.refreshTokens().then(success => {
                  if (success) {
                      console.log('Token auto-refreshed successfully');
                  } else {
                      console.warn('Auto-refresh failed, user may need to login again');
                  }
              }).catch(err => {
                  console.error('Auto-refresh error:', err);
              });
          }
      }, 30000);
  }

  // =============================================
  // MÉTODOS HTTP BASE
  // =============================================

  async makeRequest(endpoint, options = {}) {
      const url = `${this.baseURL}${endpoint}`;
      const defaultOptions = {
          headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json'
          },
          ...options
      };

      // Adicionar token de autenticação se disponível
      if (this.token && !options.skipAuth) {
          defaultOptions.headers['Authorization'] = `Bearer ${this.token}`;
      }

      try {
          const response = await fetch(url, defaultOptions);
          
          // Se token expirou, tentar renovar
          if (response.status === 401 && this.refreshToken && !options.skipAuth) {
              console.log('Received 401, attempting token refresh...');
              const refreshed = await this.refreshTokens();
              if (refreshed) {
                  // Tentar novamente com novo token
                  defaultOptions.headers['Authorization'] = `Bearer ${this.token}`;
                  return await fetch(url, defaultOptions);
              } else {
                  // Refresh falhou, limpar tokens
                  this.clearTokens();
                  throw new Error('Authentication expired. Please login again.');
              }
          }

          return response;
      } catch (error) {
          console.error('API Request failed:', error);
          throw error;
      }
  }

  async get(endpoint, options = {}) {
      return this.makeRequest(endpoint, { ...options, method: 'GET' });
  }

  async post(endpoint, data = null, options = {}) {
      return this.makeRequest(endpoint, {
          ...options,
          method: 'POST',
          body: data ? JSON.stringify(data) : null
      });
  }

  async put(endpoint, data = null, options = {}) {
      return this.makeRequest(endpoint, {
          ...options,
          method: 'PUT',
          body: data ? JSON.stringify(data) : null
      });
  }

  async delete(endpoint, options = {}) {
      return this.makeRequest(endpoint, { ...options, method: 'DELETE' });
  }

  // =============================================
  // AUTENTICAÇÃO (USANDO ENDPOINTS PÚBLICOS)
  // =============================================

  async login(username, password) {
      try {
          const response = await this.post('/auth/login', {
              username: username,
              password: password
          }, { skipAuth: true });

          if (!response.ok) {
              const error = await response.json();
              throw new Error(error.message || 'Login failed');
          }

          const data = await response.json();
          
          if (data.status === 'success' && data.data) {
              this.storeTokens(data.data.token, data.data.refresh_token);
              
              // Armazenar informações do usuário
              sessionStorage.setItem('prosecure_user_info', JSON.stringify(data.data.user));
              
              return {
                  success: true,
                  user: data.data.user,
                  message: data.message
              };
          } else {
              throw new Error(data.message || 'Invalid response from server');
          }
      } catch (error) {
          console.error('Login error:', error);
          return {
              success: false,
              message: error.message
          };
      }
  }

  async refreshTokens() {
      if (!this.refreshToken) {
          return false;
      }

      try {
          const response = await this.post('/auth/refresh', {
              refresh_token: this.refreshToken
          }, { skipAuth: true });

          if (!response.ok) {
              return false;
          }

          const data = await response.json();
          
          if (data.status === 'success' && data.data) {
              this.storeTokens(data.data.token, data.data.refresh_token);
              
              // Atualizar informações do usuário se disponível
              if (data.data.user) {
                  sessionStorage.setItem('prosecure_user_info', JSON.stringify(data.data.user));
              }
              
              return true;
          }

          return false;
      } catch (error) {
          console.error('Token refresh error:', error);
          return false;
      }
  }

  async logout() {
      try {
          if (this.token) {
              await this.post('/auth/logout');
          }
      } catch (error) {
          console.error('Logout error:', error);
      } finally {
          this.clearTokens();
      }
  }

  async getUserInfo() {
      try {
          // Primeiro, tentar do sessionStorage
          const cachedUser = sessionStorage.getItem('prosecure_user_info');
          if (cachedUser) {
              return JSON.parse(cachedUser);
          }

          // Se não tiver no cache, buscar da API
          const response = await this.get('/auth/user');
          
          if (!response.ok) {
              throw new Error('Failed to get user info');
          }

          const data = await response.json();
          
          // Armazenar no cache
          sessionStorage.setItem('prosecure_user_info', JSON.stringify(data.data));
          
          return data.data;
      } catch (error) {
          console.error('Get user info error:', error);
          throw error;
      }
  }

  // =============================================
  // OPERAÇÕES DE PAGAMENTO PROTEGIDAS
  // =============================================

  async updatePaymentMethod(cardData) {
      try {
          const response = await this.post('/protected/update-payment', {
              card_name: cardData.cardName,
              card_number: cardData.cardNumber,
              expiry: cardData.expiry,
              cvv: cardData.cvv
          });

          if (!response.ok) {
              const error = await response.json();
              throw new Error(error.message || 'Failed to update payment method');
          }

          const data = await response.json();
          return {
              success: true,
              data: data.data,
              message: data.message
          };
      } catch (error) {
          console.error('Update payment method error:', error);
          return {
              success: false,
              message: error.message
          };
      }
  }

  async getAccountDetails() {
      try {
          const response = await this.get('/protected/account');
          
          if (!response.ok) {
              const error = await response.json();
              throw new Error(error.message || 'Failed to get account details');
          }

          const data = await response.json();
          return {
              success: true,
              data: data.data
          };
      } catch (error) {
          console.error('Get account details error:', error);
          return {
              success: false,
              message: error.message
          };
      }
  }

  async getPaymentHistory() {
      try {
          const response = await this.get('/protected/payment-history');
          
          if (!response.ok) {
              const error = await response.json();
              throw new Error(error.message || 'Failed to get payment history');
          }

          const data = await response.json();
          return {
              success: true,
              data: data.data
          };
      } catch (error) {
          console.error('Get payment history error:', error);
          return {
              success: false,
              message: error.message
          };
      }
  }

  async addPlan(planId, annually = false) {
      try {
          const response = await this.post('/protected/add-plan', {
              plan_id: planId,
              annually: annually
          });

          if (!response.ok) {
              const error = await response.json();
              throw new Error(error.message || 'Failed to add plan');
          }

          const data = await response.json();
          return {
              success: true,
              data: data.data,
              message: data.message
          };
      } catch (error) {
          console.error('Add plan error:', error);
          return {
              success: false,
              message: error.message
          };
      }
  }

  // =============================================
  // ENDPOINTS PÚBLICOS (sem autenticação)
  // =============================================

  async updateCardPublic(cardData) {
      try {
          const response = await this.post('/update-card', {
              email: cardData.email,
              username: cardData.username,
              card_name: cardData.cardName,
              card_number: cardData.cardNumber,
              expiry: cardData.expiry,
              cvv: cardData.cvv
          }, { skipAuth: true });

          if (!response.ok) {
              const error = await response.json();
              throw new Error(error.message || 'Failed to update card');
          }

          const data = await response.json();
          return data;
      } catch (error) {
          console.error('Update card public error:', error);
          throw error;
      }
  }

  async checkAccountStatus(email, username) {
      try {
          const response = await this.get(`/check-account-status?email=${encodeURIComponent(email)}&username=${encodeURIComponent(username)}`, { skipAuth: true });
          
          if (!response.ok) {
              const error = await response.json();
              throw new Error(error.message || 'Failed to check account status');
          }

          return await response.json();
      } catch (error) {
          console.error('Check account status error:', error);
          throw error;
      }
  }

  // =============================================
  // UTILITÁRIOS E HELPERS
  // =============================================

  // Integração com sistema PHP existente
  async authenticateFromPHP(username, password) {
      const result = await this.login(username, password);
      
      if (result.success) {
          return {
              authenticated: true,
              user: result.user,
              accountType: result.user.account_type,
              isMaster: result.user.is_master,
              needsPaymentUpdate: result.user.account_type === 'payment_error'
          };
      } else {
          return {
              authenticated: false,
              message: result.message
          };
      }
  }

  // Validar se o usuário está autenticado e retornar info
  async validateCurrentSession() {
      if (!this.isAuthenticated()) {
          return { valid: false, message: 'Not authenticated' };
      }

      try {
          const userInfo = await this.getUserInfo();
          return {
              valid: true,
              user: userInfo
          };
      } catch (error) {
          this.clearTokens();
          return {
              valid: false,
              message: 'Session expired'
          };
      }
  }

  // Helper para verificar permissões
  async hasPermission(requiredRole) {
      try {
          const userInfo = await this.getUserInfo();
          
          switch (requiredRole) {
              case 'master':
                  return userInfo.is_master === true;
              case 'active':
                  return ['master', 'normal'].includes(userInfo.account_type);
              case 'payment_error':
                  return userInfo.account_type === 'payment_error';
              default:
                  return true;
          }
      } catch (error) {
          return false;
      }
  }

  // Helper para mostrar loading e erro em forms
  static showLoading(element, show = true) {
      if (show) {
          $(element).prop('disabled', true).addClass('loading');
      } else {
          $(element).prop('disabled', false).removeClass('loading');
      }
  }

  static showError(message, container = 'body') {
      const alertHtml = `
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <i class="fas fa-exclamation-triangle me-2"></i>
              ${message}
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
      `;
      $(container).prepend(alertHtml);
  }

  static showSuccess(message, container = 'body') {
      const alertHtml = `
          <div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>
              ${message}
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
      `;
      $(container).prepend(alertHtml);
  }

  // NOVA FUNCIONALIDADE: Status da autenticação
  getAuthStatus() {
      return {
          isAuthenticated: this.isAuthenticated(),
          hasToken: !!this.token,
          tokenExpired: this.isTokenExpired(),
          hasRefreshToken: !!this.refreshToken,
          user: this.isAuthenticated() ? JSON.parse(sessionStorage.getItem('prosecure_user_info') || '{}') : null
      };
  }
}

// =============================================
// INTEGRAÇÃO COM JQUERY E SISTEMA PHP
// =============================================

// Instância global da API
window.ProSecureAPI = new ProSecureAPI();

// Integração com formulários jQuery
$(document).ready(function() {
  // Auto-inicializar se há token armazenado
  if (window.ProSecureAPI.isAuthenticated()) {
      console.log('ProSecure API: User is authenticated');
      
      // Log de status detalhado
      const authStatus = window.ProSecureAPI.getAuthStatus();
      console.log('Auth Status:', authStatus);
  } else {
      console.log('ProSecure API: User not authenticated');
  }
});

// Helper functions para uso nas páginas PHP
window.prosecureLogin = async function(username, password) {
  return await window.ProSecureAPI.authenticateFromPHP(username, password);
};

window.prosecureUpdateCard = async function(cardData) {
  if (window.ProSecureAPI.isAuthenticated()) {
      return await window.ProSecureAPI.updatePaymentMethod(cardData);
  } else {
      return await window.ProSecureAPI.updateCardPublic(cardData);
  }
};

window.prosecureLogout = async function() {
  await window.ProSecureAPI.logout();
};

window.prosecureGetAccountDetails = async function() {
  return await window.ProSecureAPI.getAccountDetails();
};

window.prosecureGetUserInfo = async function() {
  try {
      return await window.ProSecureAPI.getUserInfo();
  } catch (error) {
      return null;
  }
};

window.prosecureCheckPermission = async function(role) {
  return await window.ProSecureAPI.hasPermission(role);
};

// NOVA FUNCIONALIDADE: Auto-verificação de sessão em páginas protegidas
window.prosecureRequireAuth = async function(redirectUrl = '/users/index.php?err9=1') {
  const session = await window.ProSecureAPI.validateCurrentSession();
  if (!session.valid) {
      window.location.href = redirectUrl;
      return false;
  }
  return true;
};

if (window.ProSecureAPI) {
    const originalStoreTokens = window.ProSecureAPI.storeTokens;
    
    window.ProSecureAPI.storeTokens = function(token, refreshToken) {
        console.log('🔄 storeTokens called with:');
        console.log('Token length:', token ? token.length : 'No token');
        console.log('RefreshToken length:', refreshToken ? refreshToken.length : 'No refresh token');
        
        if (!token || !refreshToken) {
            console.error('❌ Invalid tokens provided to storeTokens');
            return;
        }
        
        try {
            // Chamar método original
            const result = originalStoreTokens.call(this, token, refreshToken);
            
            // Verificar se foi armazenado corretamente
            console.log('✅ Tokens stored, verifying...');
            console.log('sessionStorage prosecure_token:', sessionStorage.getItem('prosecure_token') ? 'SET' : 'NOT SET');
            console.log('sessionStorage prosecure_refresh_token:', sessionStorage.getItem('prosecure_refresh_token') ? 'SET' : 'NOT SET');
            console.log('sessionStorage prosecure_token_expires:', sessionStorage.getItem('prosecure_token_expires'));
            
            // Verificar se getStoredToken funciona
            const retrieved = this.getStoredToken();
            console.log('getStoredToken() returns:', retrieved ? 'Token available' : 'No token');
            
            return result;
        } catch (error) {
            console.error('❌ Error in storeTokens:', error);
            throw error;
        }
    };
    
    console.log('🔧 Debug override applied to ProSecureAPI.storeTokens');
}

// Exemplo de uso em página PHP (atualizado):
/*
<script>
// Exemplo 1: Atualizar cartão para usuário autenticado
$('#update-card-form').on('submit', async function(e) {
  e.preventDefault();
  
  ProSecureAPI.showLoading('#submit-btn');
  
  try {
      const result = await prosecureUpdateCard({
          cardName: $('#card-name').val(),
          cardNumber: $('#card-number').val(),
          expiry: $('#expiry').val(),
          cvv: $('#cvv').val()
      });
      
      if (result.success) {
          ProSecureAPI.showSuccess('Card updated successfully!');
      } else {
          ProSecureAPI.showError(result.message);
      }
  } catch (error) {
      ProSecureAPI.showError('An error occurred: ' + error.message);
  } finally {
      ProSecureAPI.showLoading('#submit-btn', false);
  }
});

// Exemplo 2: Login integrado (atualizado)
$('#login-form').on('submit', async function(e) {
  e.preventDefault();
  
  const result = await prosecureLogin($('#username').val(), $('#password').val());
  
  if (result.authenticated) {
      if (result.needsPaymentUpdate) {
          window.location.href = '/users/update_card.php';
      } else {
          window.location.href = '/users/dashboard/';
      }
  } else {
      ProSecureAPI.showError(result.message);
  }
});

// Exemplo 3: Verificar autenticação em página protegida
$(document).ready(async function() {
  // Verificar se o usuário está autenticado
  const authRequired = await prosecureRequireAuth();
  if (!authRequired) return;
  
  // Verificar permissões específicas
  const isMaster = await prosecureCheckPermission('master');
  if (isMaster) {
      $('#master-only-content').show();
  }
  
  // Carregar informações do usuário
  const userInfo = await prosecureGetUserInfo();
  if (userInfo) {
      $('#user-name').text(userInfo.username);
      $('#account-type').text(userInfo.account_type);
  }
});
</script>
*/