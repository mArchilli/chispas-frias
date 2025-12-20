export default function Footer() {
    return (
        <footer className="bg-navy text-chalk py-12">
            <div className="max-w-7xl mx-auto px-6 lg:px-8">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div>
                        <h3 className="text-gold text-lg font-semibold mb-4">Sobre Nosotros</h3>
                        <p className="text-sm">
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                        </p>
                    </div>
                    <div>
                        <h3 className="text-gold text-lg font-semibold mb-4">Enlaces Rápidos</h3>
                        <ul className="space-y-2 text-sm">
                            <li><a href="#" className="hover:text-gold transition">Productos</a></li>
                            <li><a href="#" className="hover:text-gold transition">Servicios</a></li>
                            <li><a href="#" className="hover:text-gold transition">Galería</a></li>
                            <li><a href="#" className="hover:text-gold transition">Contacto</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 className="text-gold text-lg font-semibold mb-4">Contacto</h3>
                        <p className="text-sm">
                            Lorem ipsum dolor sit amet<br />
                            consectetur adipiscing elit<br />
                            Sed do eiusmod tempor<br />
                            +54 123 456 7890
                        </p>
                    </div>
                </div>
                <div className="border-t border-chalk/20 mt-8 pt-8 text-center text-sm">
                    <p>&copy; {new Date().getFullYear()} Chispas Frías. Todos los derechos reservados.</p>
                </div>
            </div>
        </footer>
    );
}
