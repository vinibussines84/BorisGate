import React from "react";
import { Navigate } from "react-router-dom";

export default function ProtectedRouteDashRash({ user, children }) {

  // ❗ Bloqueia caso não tenha login
  if (!user) {
    return <Navigate to="/login" replace />;
  }

  // ❗ Somente dashrash === 1 pode acessar
  if (user.dashrash !== 1) {
    return <Navigate to="/403" replace />;
  }

  return children;
}
