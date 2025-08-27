#!/usr/bin/env python3
"""
Overpass AI Bridge - Python Operations Handler
Handles embedding generation, vector search, and other AI operations
"""

import json
import sys
import sqlite3
import numpy as np
from typing import Dict, List, Any
import os
from dotenv import load_dotenv
from openai import OpenAI

# Load environment variables from parent directory
env_path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), '.env')
load_dotenv(env_path)

# Initialize OpenAI client
api_key = os.getenv('OPENAI_API_KEY')
if not api_key:
    # Try to get from command line environment
    api_key = os.environ.get('OPENAI_API_KEY')

client = OpenAI(api_key=api_key) if api_key else None

def cosine_similarity(a: np.ndarray, b: np.ndarray) -> float:
    """Calculate cosine similarity between two vectors."""
    dot_product = np.dot(a, b)
    norm_a = np.linalg.norm(a)
    norm_b = np.linalg.norm(b)
    
    if norm_a == 0 or norm_b == 0:
        return 0.0
    
    return dot_product / (norm_a * norm_b)

def health_check(payload: Dict[str, Any]) -> Dict[str, Any]:
    """Health check to verify Python bridge is working."""
    return {
        "success": True,
        "data": {
            "status": "healthy",
            "python_version": sys.version,
            "openai_available": client is not None
        }
    }

def sqlite_search(payload: Dict[str, Any]) -> Dict[str, Any]:
    """
    Search for similar chunks using cosine similarity on embeddings stored in SQLite.
    
    Expected payload:
    - db_path: Path to SQLite database
    - document_id: Document ID to search within
    - query: Query text to search for
    - k: Number of top results to return (default: 5)
    - min_score: Minimum similarity score threshold (default: 0.2)
    """
    try:
        # Extract parameters
        db_path = payload.get('db_path')
        document_id = payload.get('document_id')
        query_text = payload.get('query')
        k = payload.get('k', 5)
        min_score = payload.get('min_score', 0.2)
        
        if not all([db_path, document_id, query_text]):
            raise ValueError("Missing required parameters: db_path, document_id, or query")
        
        # Generate embedding for the query
        response = client.embeddings.create(
            model="text-embedding-3-small",
            input=query_text
        )
        query_embedding = np.array(response.data[0].embedding)
        
        # Connect to SQLite database
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        
        # Fetch chunks for the document
        cursor.execute("""
            SELECT id, content, embedding_json 
            FROM chunks 
            WHERE document_id = ? 
            ORDER BY chunk_index
        """, (document_id,))
        
        chunks = cursor.fetchall()
        conn.close()
        
        if not chunks:
            return {
                "success": True,
                "data": {
                    "results": []
                }
            }
        
        # Calculate similarities
        results = []
        for chunk_id, content, embedding_json in chunks:
            try:
                chunk_embedding = np.array(json.loads(embedding_json))
                similarity = cosine_similarity(query_embedding, chunk_embedding)
                
                if similarity >= min_score:
                    results.append({
                        "chunk_id": chunk_id,
                        "score": float(similarity),
                        "preview": content[:200] + "..." if len(content) > 200 else content
                    })
            except (json.JSONDecodeError, ValueError) as e:
                # Skip chunks with invalid embeddings
                continue
        
        # Sort by score and take top K
        results = sorted(results, key=lambda x: x['score'], reverse=True)[:k]
        
        return {
            "success": True,
            "data": {
                "results": results
            }
        }
    
    except Exception as e:
        return {
            "success": False,
            "error": str(e)
        }

def vector_search(payload: Dict[str, Any]) -> Dict[str, Any]:
    """
    Generic vector search operation for in-memory vectors.
    
    Expected payload:
    - vectors: List of vectors to search through
    - query: Query vector
    - k: Number of top results to return
    """
    try:
        vectors = payload.get('vectors', [])
        query = np.array(payload.get('query', []))
        k = payload.get('k', 5)
        
        if len(query) == 0 or len(vectors) == 0:
            return {
                "success": True,
                "data": {
                    "results": []
                }
            }
        
        similarities = []
        for i, vector in enumerate(vectors):
            similarity = cosine_similarity(query, np.array(vector))
            similarities.append({
                "index": i,
                "score": float(similarity)
            })
        
        # Sort and take top K
        similarities = sorted(similarities, key=lambda x: x['score'], reverse=True)[:k]
        
        return {
            "success": True,
            "data": {
                "results": similarities
            }
        }
    
    except Exception as e:
        return {
            "success": False,
            "error": str(e)
        }

def main():
    """Main entry point for the Python bridge."""
    if len(sys.argv) < 2:
        print(json.dumps({
            "success": False,
            "error": "No input provided"
        }))
        sys.exit(1)
    
    try:
        # Parse input JSON
        input_data = json.loads(sys.argv[1])
        operation = input_data.get('operation')
        payload = input_data.get('payload', {})
        
        # Route to appropriate handler
        handlers = {
            'health_check': health_check,
            'sqlite_search': sqlite_search,
            'vector_search': vector_search,
        }
        
        if operation not in handlers:
            result = {
                "success": False,
                "error": f"Unknown operation: {operation}"
            }
        else:
            result = handlers[operation](payload)
        
        # Output result as JSON
        print(json.dumps(result))
        
    except json.JSONDecodeError as e:
        print(json.dumps({
            "success": False,
            "error": f"Invalid JSON input: {str(e)}"
        }))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": f"Unexpected error: {str(e)}"
        }))
        sys.exit(1)

if __name__ == "__main__":
    main()