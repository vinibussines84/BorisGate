#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import argparse, json, os, cv2, numpy as np, pytesseract
from PIL import Image
import insightface
from insightface.app import FaceAnalysis
from numpy.linalg import norm

# ===========================
# Helpers
# ===========================

def read_img(path):
    if not path or not os.path.exists(path):
        return None
    img = cv2.imread(path)
    if img is None:
        return None
    return img

def variance_of_laplacian(image):
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    return cv2.Laplacian(gray, cv2.CV_64F).var()

def brightness(image):
    hsv = cv2.cvtColor(image, cv2.COLOR_BGR2HSV)
    return float(np.mean(hsv[:,:,2]))

def cosine_sim(a, b):
    return float(np.dot(a, b) / (norm(a) * norm(b) + 1e-8))

def looks_like_document(img):
    """
    Heurísticas simples de 'parece documento':
    - contornos grandes retangulares (cartão/rg/cnh)
    - densidade de texto via OCR
    """
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    blur = cv2.GaussianBlur(gray, (5,5), 0)
    edged = cv2.Canny(blur, 50, 150)
    contours, _ = cv2.findContours(edged, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    rect_like = 0
    h, w = gray.shape[:2]
    for c in contours:
        peri = cv2.arcLength(c, True)
        approx = cv2.approxPolyDP(c, 0.03*peri, True)
        area = cv2.contourArea(c)
        # grandes polígonos 4~5 lados (cartões/documentos)
        if len(approx) in (4,5) and area > (w*h*0.1):
            rect_like += 1

    # OCR (quanto texto existe?)
    pil = Image.fromarray(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))
    txt = pytesseract.image_to_string(pil)
    text_ratio = len(txt.strip()) / max(w*h/5000, 1)

    return rect_like >= 1 and text_ratio > 0.02

# ===========================
# Main
# ===========================

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--selfie", default=None, help="caminho da selfie")
    parser.add_argument("--doc_front", default=None, help="caminho do documento (frente)")
    parser.add_argument("--doc_back", default=None, help="caminho do documento (verso)")
    args = parser.parse_args()

    # Inicializa modelo de face (CPU)
    app = FaceAnalysis(name="buffalo_l", providers=["CPUExecutionProvider"])
    app.prepare(ctx_id=0, det_size=(640,640))

    reasons = []
    metrics = {}

    # Limiares (ajuste conforme sua base real)
    MIN_FACES_SELFIE = 1
    MAX_FACES_SELFIE = 1
    MIN_BLUR_VAR     = 80.0   # foco mínimo (quanto maior melhor)
    MIN_BRIGHTNESS   = 40.0   # brilho mínimo (0-255)
    MAX_BRIGHTNESS   = 230.0  # brilho máximo
    MIN_SIMILARITY   = 0.38   # ~similaridade cosine (selfie x doc face)

    # ---------- SELFIE ----------
    selfie_emb = None
    if args.selfie:
        img = read_img(args.selfie)
        if img is None:
            reasons.append("Selfie inválida ou corrompida.")
        else:
            faces = app.get(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))
            metrics["selfie_faces"] = len(faces)
            metrics["selfie_blur"]  = variance_of_laplacian(img)
            metrics["selfie_light"] = brightness(img)

            if not (MIN_FACES_SELFIE <= len(faces) <= MAX_FACES_SELFIE):
                reasons.append("A selfie deve conter exatamente 1 rosto.")
            if metrics["selfie_blur"] < MIN_BLUR_VAR:
                reasons.append("Selfie desfocada. Reenvie mais nítida.")
            if not (MIN_BRIGHTNESS <= metrics["selfie_light"] <= MAX_BRIGHTNESS):
                reasons.append("Iluminação inadequada na selfie.")

            if faces:
                selfie_emb = faces[0].normed_embedding
    else:
        reasons.append("Envie uma selfie.")

    # ---------- DOC FRONT ----------
    doc_emb = None
    if args.doc_front:
        img = read_img(args.doc_front)
        if img is None:
            reasons.append("Documento (frente) inválido ou corrompido.")
        else:
            faces = app.get(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))
            metrics["doc_front_faces"] = len(faces)
            metrics["doc_front_blur"]  = variance_of_laplacian(img)
            metrics["doc_front_light"] = brightness(img)
            metrics["doc_front_looks_like_doc"] = 1.0 if looks_like_document(img) else 0.0

            if metrics["doc_front_looks_like_doc"] < 1.0:
                reasons.append("A imagem da frente não parece um documento válido.")
            if faces:
                doc_emb = faces[0].normed_embedding

    # ---------- DOC BACK (opcional: só heurística) ----------
    if args.doc_back:
        img = read_img(args.doc_back)
        if img is not None:
            metrics["doc_back_looks_like_doc"] = 1.0 if looks_like_document(img) else 0.0
            if metrics["doc_back_looks_like_doc"] < 1.0:
                reasons.append("A imagem do verso não parece um documento válido.")

    # ---------- MATCH SELFIE x DOC ----------
    if selfie_emb is not None and doc_emb is not None:
        sim = cosine_sim(selfie_emb, doc_emb)
        metrics["selfie_doc_similarity"] = sim
        if sim < MIN_SIMILARITY:
            reasons.append("Selfie não confere com a foto do documento.")
    else:
        reasons.append("Não foi possível verificar correspondência entre selfie e documento.")

    status = "approved" if len(reasons) == 0 else "rejected"
    print(json.dumps({"status": status, "reasons": reasons, "metrics": metrics}, ensure_ascii=False))

if __name__ == "__main__":
    main()
