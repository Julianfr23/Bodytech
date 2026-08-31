import { NavLink, Route, Routes } from 'react-router-dom';
import CustomerList from './pages/CustomerList.jsx';
import CustomerForm from './pages/CustomerForm.jsx';
import CustomerDetail from './pages/CustomerDetail.jsx';
import SubscriptionList from './pages/SubscriptionList.jsx';
import SubscriptionForm from './pages/SubscriptionForm.jsx';
import SubscriptionDetail from './pages/SubscriptionDetail.jsx';
import Engine from './pages/Engine.jsx';

export default function App() {
  return (
    <div className="app">
      <header className="topbar">
        <span className="brand">Motor de suscripciones</span>
        <nav>
          <NavLink to="/" end>Clientes</NavLink>
          <NavLink to="/subscriptions">Suscripciones</NavLink>
          <NavLink to="/engine">Motor de cobro</NavLink>
        </nav>
      </header>

      <main className="content">
        <Routes>
          <Route path="/" element={<CustomerList />} />
          <Route path="/customers/new" element={<CustomerForm />} />
          <Route path="/customers/:id" element={<CustomerDetail />} />
          <Route path="/customers/:id/edit" element={<CustomerForm />} />

          <Route path="/subscriptions" element={<SubscriptionList />} />
          <Route path="/subscriptions/new" element={<SubscriptionForm />} />
          <Route path="/subscriptions/:id" element={<SubscriptionDetail />} />
          <Route path="/subscriptions/:id/edit" element={<SubscriptionForm />} />

          <Route path="/engine" element={<Engine />} />
        </Routes>
      </main>
    </div>
  );
}
