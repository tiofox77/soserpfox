import { CopiasDeSeguranca } from '../copias/CopiasDeSeguranca';

/** As cópias só com os dados desta empresa — quem gere a conta. */
export default function Copias() {
    return <CopiasDeSeguranca ambito="empresa" />;
}
