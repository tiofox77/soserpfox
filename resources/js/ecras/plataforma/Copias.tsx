import { CopiasDeSeguranca } from '../copias/CopiasDeSeguranca';

/** As cópias da base de dados inteira — só o super admin da plataforma. */
export default function Copias() {
    return <CopiasDeSeguranca ambito="plataforma" />;
}
