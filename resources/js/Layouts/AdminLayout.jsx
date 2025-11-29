// resources/js/Layouts/AdminLayout.jsx
import React from "react";
import Topbar from "@/Components/Topbar"; // se tiver

function AdminLayout({ children, title }) {
  return (
    <div className="min-h-screen bg-[#0b0b0c] text-white">
      {/* cabeçalho / topbar */}
      <Topbar title={title} />
      <main className="max-w-7xl mx-auto p-6">{children}</main>
    </div>
  );
}

export default AdminLayout; // <<— importante
