// Facoltativo: intercettiamo la risposta JSON e mostriamo feedback senza ricaricare
document.getElementById('regForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);
    const res = await fetch(form.action, {method: 'POST', body: data});
    const json = await res.json();

    const msgDiv = document.getElementById('message');
    if (json.success) {
        msgDiv.textContent = 'Registrazione avvenuta con successo!';
        form.reset();
    } else {
        msgDiv.innerHTML = json.errors.map(e => `<p style="color:red;">${e}</p>`).join('');
    }
});
