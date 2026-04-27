// Punto di avvio di Stimulus per l'integrazione con Symfony UX.
// startStimulusApp() registra automaticamente tutti i controller
// presenti in assets/controllers/ tramite import map di Webpack Encore.
import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();
