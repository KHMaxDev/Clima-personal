import { lazy, Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import ActivityCalendar from './components/ActivityCalendar';
import ActivityCreate from './components/ActivityCreate';
import ActivityForm from './components/ActivityForm';
import AssessmentPanel from './components/AssessmentPanel';
import Assistant from './components/Assistant';
import Dashboard from './components/Dashboard';
import Forecast from './components/Forecast';
import SidebarNav from './components/SidebarNav';
import ThemeToggle from './components/ThemeToggle';
import Toaster from './components/Toaster';
import WeatherAmbience from './components/WeatherAmbience';
import WeatherStatus from './components/WeatherStatus';

/**
 * La landing carga GSAP + ScrollTrigger; se sirve en su propio chunk para no
 * penalizar el resto de la aplicación.
 */
const LandingExperience = lazy(() => import('./components/LandingExperience'));

const registry = {
    ActivityCalendar,
    ActivityCreate,
    ActivityForm,
    AssessmentPanel,
    Assistant,
    Dashboard,
    Forecast,
    LandingExperience,
    SidebarNav,
    ThemeToggle,
    WeatherAmbience,
    WeatherStatus,
};

function mount(root) {
    const name = root.dataset.reactComponent;
    const Component = registry[name];

    if (!Component) {
        console.warn(`Componente React desconocido: ${name}`);
        return;
    }

    let props = {};

    if (root.dataset.reactProps) {
        try {
            props = JSON.parse(root.dataset.reactProps);
        } catch (error) {
            console.error(`Props inválidas para ${name}`, error);
        }
    }

    createRoot(root).render(
        <Suspense fallback={null}>
            <Component {...props} />
        </Suspense>,
    );
}

document.querySelectorAll('[data-react-component]').forEach(mount);

const toasterHost = document.createElement('div');
toasterHost.dataset.reactToaster = '';
document.body.appendChild(toasterHost);
createRoot(toasterHost).render(<Toaster />);

export { mount };
