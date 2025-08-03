class SessionClient {
    static checkSession() {
        return fetch('LTDW-project/api/session_check.php', {
            method: 'GET',
            credentials: 'same-origin'
        })
            .then(response => response.json())
            .then(data => {
                if (data.expired) {
                    this.showSessionWarning();
                }
                return data;
            });
    }

    static showSessionWarning() {
        const warning = document.createElement('div');
        warning.id = 'session-warning';
        warning.innerHTML = `
            <div class="session-warning-content">
                <p>La tua sessione sta per scadere. Vuoi continuare?</p>
                <button id="extend-session">Sì, continua</button>
                <button id="logout-now">Esci</button>
            </div>
        `;
        document.body.appendChild(warning);

        document.getElementById('extend-session').addEventListener('click', () => {
            this.extendSession();
            warning.remove();
        });

        document.getElementById('logout-now').addEventListener('click', () => {
            window.location.href = '/logout.php';
        });
    }

    static extendSession() {
        fetch('LTDW-project/api/session_check.php', {
            method: 'POST',
            credentials: 'same-origin'
        }).then(r => null );
    }

    static initSessionTimer() {
        // Controlla ogni 5 minuti
        setInterval(() => this.checkSession(), 300000);
    }
}

// Avvia il controllo della sessione
document.addEventListener('DOMContentLoaded', () => {
    SessionClient.initSessionTimer();
});