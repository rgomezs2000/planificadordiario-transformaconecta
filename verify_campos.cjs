/**
 * TEMPORAL: prueba de Planificador.Formulario.nombreDeCampo().
 *
 * Laravel devuelve los campos con puntos (goals.0.description) y el HTML los
 * nombra con corchetes (goals[0][description]). Se carga el script real con un
 * jQuery simulado y se comprueba la traducción.
 */

const fs = require('node:fs');
const path = require('node:path');

let fallos = 0;

function jq(valor) {
    // document ready: no se ejecuta nada
    if (typeof valor === 'function') {
        return undefined;
    }

    return { length: 0, each() { return this; }, on() { return this; } };
}
jq.fn = {};
jq.ajaxSetup = function () {};

const ventana = { Planificador: {}, location: { pathname: '/', origin: 'http://localhost' } };

new Function('window', 'jQuery', 'document',
    fs.readFileSync(path.join(__dirname, 'public', 'js', 'script.js'), 'utf8'))(ventana, jq, {});

const Formulario = ventana.Planificador.Formulario;

if (! Formulario) {
    console.log('FALLA no se pudo cargar Planificador.Formulario');
    process.exit(1);
}

const casos = {
    'plan_date': 'plan_date',
    'energy_level_id': 'energy_level_id',
    'achievements': 'achievements',
    'goals.0.description': 'goals[0][description]',
    'goals.2.slot': 'goals[2][slot]',
    'schedule.1.activity': 'schedule[1][activity]',
    'schedule.0.start_time': 'schedule[0][start_time]',
    'preparation.3.preparation_items_description': 'preparation[3][preparation_items_description]',
    'action_blocks.0.finished_at': 'action_blocks[0][finished_at]'
};

console.log('=== NOMBRE DE CAMPO QUE DEVUELVE LARAVEL -> SELECTOR HTML ===');

for (const [entrada, esperado] of Object.entries(casos)) {
    const obtenido = Formulario.nombreDeCampo(entrada);
    const bien = obtenido === esperado;
    if (! bien) { fallos++; }

    console.log(`  ${bien ? 'OK  ' : 'FALLA'} ${entrada.padEnd(50)} ${obtenido}` +
        (bien ? '' : `  <- esperado: ${esperado}`));
}

console.log('\n=== RESULTADO ===');
console.log(fallos === 0 ? 'Todas las comprobaciones pasaron.' : `Comprobaciones fallidas: ${fallos}`);
process.exit(fallos === 0 ? 0 : 1);
