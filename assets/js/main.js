import { AuthManager } from './utils/auth.js';
import { Dashboard } from './dashboard.js';
import { GameManager } from './games/gamemanager.js';
import { showConfetti } from './utils/confetti.js';
import { updateUserStats } from './utils/user.js';

class PflegeApp {
    constructor() {
        this.auth = new AuthManager();
        this.dashboard = new Dashboard();
        this.gameManager = new GameManager();
        
        this.init();
    }

    init() {
        // Check auth on load
        document.addEventListener('DOMContentLoaded', async () => {
            const isLoggedIn = await this.auth.checkAuth();
            
            if (isLoggedIn) {
                this.showDashboard();
            } else {
                this.showLogin();
            }
            
            this.setupEventListeners();
        });
    }

    setupEventListeners() {
        // Auth
        document.getElementById('loginBtn').addEventListener('click', () => this.auth.login());
        document.getElementById('registerBtn').addEventListener('click', () => this.auth.register());
        document.getElementById('logoutBtn').addEventListener('click', () => this.logout());
        
        // Navigation
        document.getElementById('backBtn').addEventListener('click', () => this.showDashboard());
        document.getElementById('resetBtn').addEventListener('click', () => this.resetModule());
    }

    showLogin() {
        document.getElementById('loginModal').classList.add('active');
        document.getElementById('dashboard').style.display = 'none';
        document.getElementById('gameModule').style.display = 'none';
    }

    showDashboard() {
        document.getElementById('loginModal').classList.remove('active');
        document.getElementById('dashboard').style.display = 'block';
        document.getElementById('gameModule').style.display = 'none';
        
        this.dashboard.load();
        updateUserStats();
    }

    showGameModule(category) {
        document.getElementById('dashboard').style.display = 'none';
        document.getElementById('gameModule').style.display = 'block';
        
        this.gameManager.init(category);
        updateUserStats();
    }

    resetModule() {
        if (!confirm('Möchtest du dieses Modul wirklich zurücksetzen? Dein Fortschritt geht verloren, aber Münzen und XP bleiben erhalten.')) {
            return;
        }

        const categoryId = this.gameManager.currentCategory?.id;
        if (!categoryId) return;

        fetch('api/games.php?action=reset', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `category_id=${categoryId}`
        })
        .then(() => {
            this.showDashboard();
            showConfetti();
        });
    }

    async logout() {
        await this.auth.logout();
        this.showLogin();
    }
}

// Initialize app
const app = new PflegeApp();
export default app;